<?php
namespace openpsa\installer;
use Composer\Installer\LibraryInstaller as base_installer;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;

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
        parent::uninstall($repo, $package);

        $linker = new linker(dirname($this->vendorDir), $this->io);
        $linker->uninstall($this->getPackageBasePath($package));
    }

    public static function setup_root_package($event)
    {
        $basedir = realpath('./');
        $linker = new linker($basedir, $event->getIO());
        $linker->install($basedir);
    }

    public static function prepare_database($event)
    {
        $basedir = realpath('./');
        $setup = new mgd2setup($basedir, $event->getIO());
        $setup->run();
    }
}