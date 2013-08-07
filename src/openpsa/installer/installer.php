<?php
namespace openpsa\installer;
use Composer\Installer\LibraryInstaller as base_installer;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;

class installer extends base_installer
{
    private $_type;

    /**
     * {@inheritDoc}
     */
    public function supports($packageType)
    {
        $this->_type = $packageType;
        return $packageType === 'midcom-site' || $packageType === 'midcom-extras';
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
}