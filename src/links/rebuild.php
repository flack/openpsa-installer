<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer\links;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use openpsa\installer\linker;

/**
 * Removes and recreates all symlinks managed by the installer
 *
 * Covers all installed packages of type midcom-package (as listed in
 * the vendor dir's composer/installed.json) plus the root package
 *
 * @package openpsa.installer
 */
#[AsCommand(name: 'links:rebuild', description: 'Delete and recreate all symlinks managed by the installer')]
class rebuild extends Command
{
    /**
     * The root package path
     */
    private ?string $basepath = null;

    public function set_basepath(string $basepath) : void
    {
        $this->basepath = $basepath;
    }

    protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $basepath = $this->basepath ?? realpath('./');
        $linker = new linker($basepath, $input, $output, $this->getHelperSet());

        // dependencies first, root package last (same order as during composer install)
        $paths = $this->get_package_paths($basepath);
        $paths[] = $basepath;

        foreach ($paths as $path) {
            $linker->uninstall($path);
        }
        $linker->remove_dangling_links();

        foreach ($paths as $path) {
            $linker->install($path);
        }
        $output->writeln('Rebuilt links for <info>' . count($paths) . '</info> package(s)');

        return Command::SUCCESS;
    }

    /**
     * @return string[] Absolute paths of all installed midcom-packages
     */
    private function get_package_paths(string $basepath) : array
    {
        $vendor_dir = $basepath . '/' . $this->get_vendor_dir($basepath);
        $installed_file = $vendor_dir . '/composer/installed.json';
        if (!is_readable($installed_file)) {
            throw new \RuntimeException('Could not find ' . $installed_file . ', please run composer install first');
        }
        $installed = json_decode(file_get_contents($installed_file), true, flags: JSON_THROW_ON_ERROR);

        $paths = [];
        foreach ($installed['packages'] ?? [] as $package) {
            if (($package['type'] ?? null) !== 'midcom-package' || empty($package['install-path'])) {
                continue;
            }
            if ($path = realpath($vendor_dir . '/composer/' . $package['install-path'])) {
                $paths[] = $path;
            }
        }
        return $paths;
    }

    private function get_vendor_dir(string $basepath) : string
    {
        $composer_file = $basepath . '/composer.json';
        if (is_readable($composer_file)) {
            $config = json_decode(file_get_contents($composer_file), true);
            if (!empty($config['config']['vendor-dir'])) {
                return rtrim($config['config']['vendor-dir'], '/');
            }
        }
        return 'vendor';
    }
}
