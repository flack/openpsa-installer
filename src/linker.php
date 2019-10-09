<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Question\Question;

/**
 * Link management service
 *
 * @package openpsa.installer
 */
class linker
{
    private $themes_dir = '/themes';
    private $schemas_dir = '/schemas';
    private $static_dir = '/static';
    private $schema_location = '/usr/share/midgard2/schema/';

    private $readonly_behavior;

    private $basepath;

    private $links = [];

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var HelperSet
     */
    private $helperset;

    /**
     * Default constructor
     *
     * @param string $basepath The root package path
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function __construct($basepath, InputInterface $input, OutputInterface $output, HelperSet $helperset)
    {
        $this->basepath = $basepath;
        $this->input = $input;
        $this->output = $output;
        $this->helperset = $helperset;

        $this->prepare_dir('var/schemas');
        $this->set_schema_location($this->basepath . '/var/schemas/');
    }

    public function get_links(string $repo_dir)
    {
        $this->links = [];
        $this->get_static_links($repo_dir);
        $this->get_theme_links($repo_dir);
        $this->get_schema_links($repo_dir);
        return $this->links;
    }

    /**
     * Package installation routine
     *
     * @param string $path The package path
     */
    public function install(string $path)
    {
        foreach ($this->get_links($path) as $linkdata) {
            $this->link($linkdata['target'], $linkdata['linkname'], $linkdata['target_path']);
        }
    }

    /**
     * Package uninstallation routine
     *
     * @param string $path The package path
     */
    public function uninstall(string $path)
    {
        foreach ($this->get_links($path) as $linkdata) {
            $this->unlink($linkdata['linkname']);
        }
    }

    /**
     * Package uninstallation routine
     *
     * @param string $path The package path
     * @param array $oldlinks Existing links from the former package version
     */
    public function update(string $path, array $oldlinks)
    {
        foreach ($oldlinks as $linkdata) {
            $target_path = $linkdata['target_path'] ?: realpath($linkdata['target']);
            if (!file_exists($target_path)) {
                $this->unlink($linkdata['linkname']);
            }
        }
        $this->install($path);
    }

    public function set_schema_location(string $path)
    {
        $this->schema_location = $path;
    }

    public function unlink(string $linkname)
    {
        if (is_link($linkname)) {
            $this->output->writeln('Removing link <info>' . $linkname . '</info>');
            @unlink($linkname);
        }
    }

    /**
     * Direct access to link functionality
     *
     * @param string $target
     * @param string $linkname
     * @param string $target_path
     */
    public function link(string $target, $linkname, string $target_path = null)
    {
        if (null === $target_path) {
            $target_path = $target;
        }
        $target_path = realpath($target_path);

        if (!file_exists($target_path)) {
            throw new \Exception('Cannot link to nonexistent path ' . $target);
        }

        if (is_link($linkname)) {
            if (!file_exists(realpath($linkname))) {
                $this->output->writeln('Link in <info>' . basename($target) . '</info> points to nonexistent path, removing');
                @unlink($linkname);
            } else {
                if (   realpath($linkname) !== $target_path
                    && md5_file(realpath($linkname)) !== md5_file($target_path)) {
                    $this->output->writeln('Skipping <info>' . basename($target) . '</info>: Found Link in <info>' . dirname($linkname) . '</info> to <comment>' . realpath($linkname) . '</comment>');
                }
                return;
            }
        } elseif (is_file($linkname)) {
            if (md5_file($linkname) !== md5_file($target_path)) {
                $this->output->writeln('Skipping <info>' . basename($target) . '</info>: Found existing file in <comment>' . dirname($linkname) . '</comment>');
            }
            return;
        }

        if (!is_writeable(dirname($linkname))) {
            if ($this->readonly_behavior === null) {
                $this->output->writeln('Directory <info>' . dirname($linkname) . '</info> is not writeable.');
                $dialog = $this->helperset->get('question');
                $reply = $dialog->ask($this->input, $this->output, new Question('<question>Please choose:</question> [<comment>(S)udo</comment>, (I)gnore, (A)bort] '));
                $this->readonly_behavior = strtolower(trim($reply));
            }
            switch ($this->readonly_behavior) {
                case 'a':
                    throw new \Exception('Aborted by user command');
                case 'i':
                    $this->output->writeln('<info>Skipped linking ' . basename($linkname) . ' to ' . dirname($linkname) . '</info>');
                    return;
                case '':
                case 's':
                    exec('sudo ln -s ' . escapeshellarg($target) . ' ' . escapeshellarg($linkname), $output, $return);
                    if ($return !== 0) {
                        throw new \Exception('Failed to link ' . basename($linkname) . ' to ' . dirname($linkname));
                    }
                    break;
                default:
                    throw new \Exception('Invalid input');
            }
        } else {
            if (!@symlink($target, $linkname)) {
                $error = error_get_last();
                throw new \Exception('could not link ' . $target . ' to ' . $linkname . ': ' . $error['message']);
            }
        }
        if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $this->output->writeln('Linked <info>' . $target . '</info> to <comment>' . $linkname . '</comment>');
        }
    }

    public function remove_dangling_links()
    {
        $to_check = array_filter([
            $this->basepath . '/web/midcom-static',
            $this->basepath . '/var/themes',
            $this->schema_location
        ], 'is_dir');
        foreach ($to_check as $path) {
            $iterator = new \DirectoryIterator($path);
            foreach ($iterator as $child) {
                if ($child->isLink() && !$child->getRealPath()) {
                    $this->unlink($child->getPathname());
                }
            }
        }
    }

    private function get_static_links(string $repo_dir)
    {
        $source = $repo_dir . $this->static_dir;
        if (!is_dir($source)) {
            return;
        }
        $this->prepare_dir('web/midcom-static');
        $static_basedir = $this->basepath . '/web/midcom-static';

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child) {
            if (   $child->getType() == 'dir'
                && substr($child->getFileName(), 0, 1) !== '.') {
                $absolute_path = $child->getPathname();
                $this->links[] = [
                    'target' => $this->get_relative_path($absolute_path),
                    'linkname' => $static_basedir . '/' . $child->getFilename(),
                    'target_path' => $absolute_path
                ];
            }
        }
    }

    private function get_theme_links(string $repo_dir)
    {
        $source = $repo_dir . $this->themes_dir;
        if (!is_dir($source)) {
            return;
        }
        $this->prepare_dir('web/midcom-static');
        $this->prepare_dir('var/themes');
        $static_basedir = $this->basepath . '/web/midcom-static';
        $themes_dir = $this->basepath . '/var/themes';

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child) {
            if (   $child->getType() == 'dir'
                && substr($child->getFileName(), 0, 1) !== '.') {
                // link theme
                $absolute_path = $child->getPathname();
                $this->links[] = [
                    'target' => $this->get_relative_path($absolute_path),
                    'linkname' => $themes_dir . '/' . $child->getFilename(),
                    'target_path' => $absolute_path
                ];

                // link themes "static" folder
                if (is_dir($child->getPathname() . '/static')) {
                    $absolute_path = $child->getPathname() . '/static';
                    $this->links[] = [
                        'target' => $this->get_relative_path($absolute_path),
                        'linkname' => $static_basedir . '/' . $child->getFilename(),
                        'target_path' => $absolute_path
                    ];
                }
            }
        }
    }

    private function get_schema_links(string $repo_dir)
    {
        $source = $repo_dir . $this->schemas_dir;
        if (!is_dir($source)) {
            return;
        }

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child) {
            if (   $child->getType() == 'file'
                && substr($child->getFileName(), 0, 1) !== '.'
                && substr($child->getFilename(), -4) === '.xml') {
                $absolute_path = $child->getRealPath();
                $this->links[] = [
                    'target' => $this->get_relative_path($absolute_path),
                    'linkname' => $this->schema_location . $child->getFilename(),
                    'target_path' => $absolute_path
                ];
            }
        }
    }

    private function prepare_dir(string $dir)
    {
        $fs = new Filesystem;
        $fs->mkdir($this->basepath . '/' . $dir);
    }

    private function get_relative_path(string $absolute_path, $updir_count = 2)
    {
        return str_repeat('../', $updir_count) . substr($absolute_path, strlen($this->basepath) + 1);
    }
}
