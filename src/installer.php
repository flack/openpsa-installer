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
use Symfony\Component\Filesystem\Filesystem;

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
        $linker = $this->get_linker();
        $old_links = $linker->get_links($this->getPackageBasePath($initial));

        parent::update($repo, $initial, $target);

        $linker->update($this->getPackageBasePath($target), $old_links);
    }

    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);

        $this->get_linker()->install($this->getPackageBasePath($package));
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $this->get_linker()->uninstall($this->getPackageBasePath($package));

        parent::uninstall($repo, $package);
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
        self::setup_project_directory($basedir);

        $this->get_linker($basedir)->install($basedir);
    }

    private function get_linker($dir = null)
    {
        if ($dir === null) {
            $dir = dirname($this->vendorDir);
        }
        $class = new \ReflectionClass('Composer\IO\ConsoleIO');
        $input = $class->getProperty("input");
        $input->setAccessible(true);
        $output = $class->getProperty("output");
        $output->setAccessible(true);
        $helperset = $class->getProperty("helperSet");
        $helperset->setAccessible(true);
        return new linker($dir, $input->getValue($this->io), $output->getValue($this->io), $helperset->getValue($this->io));
    }

    public static function setup_project_directory($basedir)
    {
        $fs = new Filesystem;
        $fs->mkdir($basedir . '/config');
        $fs->mkdir($basedir . '/var/cache');
        $fs->mkdir($basedir . '/var/rcs');
        $fs->mkdir($basedir . '/var/blobs');
        $fs->mkdir($basedir . '/var/log');
        $fs->mkdir($basedir . '/var/themes');
    }
}
