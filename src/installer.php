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
use Composer\Util\Filesystem;

/**
 * Simple installer class. Runs standard Composer functionality and attaches custom services where appropriate
 *
 * @package openpsa.installer
 */
class installer extends base_installer
{
    protected static $_sharedir = '/usr/share/midgard2';

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
        parent::update($repo, $initial, $target);

        $linker = new linker(dirname($this->vendorDir), $this->io);
        $linker->install($this->getPackageBasePath($target));
    }

    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);

        $linker = new linker(dirname($this->vendorDir), $this->io);
        $linker->install($this->getPackageBasePath($package));
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $linker = new linker(dirname($this->vendorDir), $this->io);
        $linker->uninstall($this->getPackageBasePath($package));

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

        $linker = new linker($basedir, $event->getIO());
        $linker->install($basedir);

        if (extension_loaded('midgard2'))
        {
            $prefix = $basedir . '/vendor/openpsa/installer/data/';
            $linker->link($prefix . 'midgard_auth_types.xml', self::$_sharedir . '/midgard_auth_types.xml');
            $linker->link($prefix . 'MidgardObjects.xml', self::$_sharedir . '/MidgardObjects.xml');
        }
    }

    public static function setup_project_directory($basedir)
    {
        $fs = new Filesystem;
        $fs->ensureDirectoryExists($basedir . '/config');
        $fs->ensureDirectoryExists($basedir . '/var/cache');
        $fs->ensureDirectoryExists($basedir . '/var/rcs');
        $fs->ensureDirectoryExists($basedir . '/var/blobs');
        $fs->ensureDirectoryExists($basedir . '/var/log');
    }
}