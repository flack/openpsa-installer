<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer;

use Composer\Installer\LibraryInstaller as base_installer;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;
use Composer\Script\Event;
use Composer\IO\ConsoleIO;

/**
 * Simple installer class. Runs standard Composer functionality and attaches custom services where appropriate
 *
 * @package openpsa.installer
 */
class installer extends base_installer
{
    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        return $packageType === 'midcom-package';
    }

    /**
     * {@inheritDoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        $linker = self::get_linker(dirname($this->vendorDir), $this->io);
        $old_links = $linker->get_links($this->getPackageBasePath($initial));
        $path = $this->getPackageBasePath($target);

        parent::update($repo, $initial, $target)
            ->then(function() use ($linker, $path, $old_links) {
                $linker->update($path, $old_links);
            });
    }

    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $linker = self::get_linker(dirname($this->vendorDir), $this->io);
        $path = $this->getPackageBasePath($package);

        parent::install($repo, $package)
            ->then(function() use ($linker, $path) {
                $linker->install($path);
            });
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $linker = self::get_linker(dirname($this->vendorDir), $this->io);
        $path = $this->getPackageBasePath($package);

        parent::uninstall($repo, $package)
            ->then(function() use ($linker, $path) {
                $linker->uninstall($path);
            });
    }

    /**
     * Links package resources to appropriate places and creates
     * the required directories inside the project directory
     *
     * @param Event $event The event we're called from
     */
    public static function setup_root_package(Event $event)
    {
        $basedir = realpath('./');
        setup::prepare_project_directory($basedir);
        $linker = self::get_linker($basedir, $event->getIO());
        if ($event->getName() === 'post-update-cmd') {
            $linker->remove_dangling_links();
        }
        $linker->install($basedir);
    }

    private static function get_linker(string $dir, ConsoleIO $io) : linker
    {
        $class = new \ReflectionClass(ConsoleIO::class);
        $input = $class->getProperty("input");
        $output = $class->getProperty("output");
        $helperset = $class->getProperty("helperSet");
        return new linker($dir, $input->getValue($io), $output->getValue($io), $helperset->getValue($io));
    }
}
