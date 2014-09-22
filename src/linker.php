<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;

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

    private $links = array();

    /**
     * Default constructor
     *
     * @param string $basepath The root package path
     * @param IOInterface $io Composer IO interface
     */
    public function __construct($basepath, IOInterface $io)
    {
        $this->basepath = $basepath;
        $this->io = $io;

        if (   !extension_loaded('midgard')
            && !extension_loaded('midgard2'))
        {
            $this->prepare_dir('var/schemas');
            $this->set_schema_location($this->basepath . '/var/schemas/');
        }
    }

    public function get_links($repo_dir)
    {
        $this->links = array();
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
    public function install($path)
    {
        foreach ($this->get_links($path) as $linkdata)
        {
            $this->link($linkdata['target'], $linkdata['linkname'], $linkdata['target_path']);
        }
    }

    /**
     * Package uninstallation routine
     *
     * @param string $path The package path
     */
    public function uninstall($path)
    {
        foreach ($this->get_links($path) as $linkdata)
        {
            $this->unlink($linkdata['linkname']);
        }
    }

    /**
     * Package uninstallation routine
     *
     * @param string $path The package path
     * @param array $oldlinks Existing links from the former package version
     */
    public function update($path, array $oldlinks)
    {
        foreach ($oldlinks as $linkdata)
        {
            $target_path = $linkdata['target_path'] ?: realpath($linkdata['target']);
            if (!file_exists($target_path))
            {
                $this->unlink($linkdata['linkname']);
            }
        }
        $this->install($path);
    }

    public function set_schema_location($path)
    {
        $this->schema_location = $path;
    }

    public function unlink($linkname)
    {
        if (is_link($linkname))
        {
            $this->io->write('Removing link <info>' . $linkname . '</info>');
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
    public function link($target, $linkname, $target_path = null)
    {
        if (null === $target_path)
        {
            $target_path = $target;
        }
        $target_path = realpath($target_path);

        if (!file_exists($target_path))
        {
            throw new \Exception('Cannot link to nonexistent path ' . $target);
        }

        if (is_link($linkname))
        {
            if (!file_exists(realpath($linkname)))
            {
                $this->io->write('Link in <info>' . basename($target) . '</info> points to nonexistent path, removing');
                @unlink($linkname);
            }
            else
            {
                if (   realpath($linkname) !== $target_path
                    && md5_file(realpath($linkname)) !== md5_file($target_path))
                {
                    $this->io->write('Skipping <info>' . basename($target) . '</info>: Found Link in <info>' . dirname($linkname) . '</info> to <comment>' . realpath($linkname) . '</comment>');
                }
                return;
            }
        }
        else if (is_file($linkname))
        {
            if (md5_file($linkname) !== md5_file($target_path))
            {
                $this->io->write('Skipping <info>' . basename($target) . '</info>: Found existing file in <comment>' . dirname($linkname) . '</comment>');
            }
            return;
        }

        if (!is_writeable(dirname($linkname)))
        {
            if ($this->readonly_behavior === null)
            {
                $this->io->write('Directory <info>' . dirname($linkname) . '</info> is not writeable.');
                $reply = $this->io->ask('<question>Please choose:</question> [<comment>(S)udo</comment>, (I)gnore, (A)bort]', 'S');
                $this->readonly_behavior = strtolower(trim($reply));
            }
            switch ($this->readonly_behavior)
            {
                case 'a':
                    throw new \Exception('Aborted by user command');
                case 'i':
                    $this->io->write('<info>Skipped linking ' . basename($linkname) . ' to ' . dirname($linkname) . '</info>');
                    return;
                case '':
                case 's':
                    exec('sudo ln -s ' . escapeshellarg($target) . ' ' . escapeshellarg($linkname), $output, $return);
                    if ($return !== 0)
                    {
                        throw new \Exception('Failed to link ' . basename($linkname) . ' to ' . dirname($linkname));
                    }
                    break;
                default:
                    throw new \Exception('Invalid input');
            }
        }
        else
        {
            if (!@symlink($target, $linkname))
            {
                $error = error_get_last();
                throw new \Exception('could not link ' . $target . ' to ' . $linkname . ': ' . $error['message']);
            }
        }
        if ($this->io->isVerbose())
        {
            $this->io->write('Linked <info>' . $target . '</info> to <comment>' . $linkname . '</comment>');
        }
    }

    private function get_static_links($repo_dir)
    {
        $source = $repo_dir . $this->static_dir;
        if (!is_dir($source))
        {
            return;
        }
        $this->prepare_dir('web/midcom-static');
        $static_basedir = $this->basepath . '/web/midcom-static';

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child)
        {
            if (   $child->getType() == 'dir'
                && substr($child->getFileName(), 0, 1) !== '.')
            {
                $absolute_path = $child->getPathname();
                $this->links[] = array
                (
                    'target' => $this->get_relative_path($absolute_path),
                    'linkname' => $static_basedir . '/' . $child->getFilename(),
                    'target_path' => $absolute_path
                );
            }
        }
    }

    private function get_theme_links($repo_dir)
    {
        $source = $repo_dir . $this->themes_dir;
        if (!is_dir($source))
        {
            return;
        }
        $this->prepare_dir('web/midcom-static');
        $this->prepare_dir('var/themes');
        $static_basedir = $this->basepath . '/web/midcom-static';
        $themes_dir = $this->basepath . '/var/themes';

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child)
        {
            if (   $child->getType() == 'dir'
                && substr($child->getFileName(), 0, 1) !== '.')
            {
                // link theme
                $absolute_path = $child->getPathname();
                $this->links[] = array(
                    'target' => $this->get_relative_path($absolute_path),
                    'linkname' => $themes_dir . '/' . $child->getFilename(),
                    'target_path' => $absolute_path
                );

                // link themes "static" folder
                if (is_dir($child->getPathname() . '/static'))
                {
                    $absolute_path = $child->getPathname() . '/static';
                    $this->links[] = array
                    (
                        'target' => $this->get_relative_path($absolute_path),
                        'linkname' => $static_basedir . '/' . $child->getFilename(),
                        'target_path' => $absolute_path
                    );
                }
            }
        }
    }

    private function get_schema_links($repo_dir)
    {
        if (extension_loaded('midgard'))
        {
            $this->io->write('<warning>Linking schemas is not supported on Midgard1 right now, please do this manually if necessary</warning>');
            return;
        }

        $source = $repo_dir . $this->schemas_dir;
        if (!is_dir($source))
        {
            return;
        }

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child)
        {
            if (   $child->getType() == 'file'
                && substr($child->getFileName(), 0, 1) !== '.'
                && substr($child->getFilename(), -4) === '.xml')
            {
                $this->links[] = array
                (
                    'target' => $child->getRealPath(),
                    'linkname' => $this->schema_location . $child->getFilename(),
                    'target_path' => null
                );
            }
        }
    }

    private function prepare_dir($dir)
    {
        $fs = new Filesystem;
        $fs->ensureDirectoryExists($this->basepath . '/' . $dir);
    }

    private function get_relative_path($absolute_path, $updir_count = 2)
    {
        return str_repeat('../', $updir_count) . substr($absolute_path, strlen($this->basepath) + 1);
    }
}
