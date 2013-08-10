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

/**
 * Simple installer class. Runs standard Composer functionality attaches custom services where appropriate
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
     * Links package resources to appropriate places
     *
     * @param Event $event The event we're called from
     */
    public static function setup_root_package(Event $event)
    {
        $basedir = realpath('./');
        $linker = new linker($basedir, $event->getIO());
        $linker->install($basedir);
    }

    /**
     * Prepares Midgard2 database
     *
     * @param Event $event The event we're called from
     */
    public static function prepare_database(Event $event)
    {
        $basedir = realpath('./');
        $setup = new mgd2setup($basedir, $event->getIO());
        $setup->run();
    }
}