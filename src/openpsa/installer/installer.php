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
        return $packageType === 'midcom-extras';
    }

    /**
     * {@inheritDoc}
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);
        $this->_install_statics($this->getPackageBasePath($package));
    }

    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);
        $this->_install_statics($this->getPackageBasePath($package));
    }

    private function _install_statics($repo_dir)
    {
        $source = $repo_dir . '/static';
        if (!is_dir($source))
        {
            return;
        }
        $target = dirname($this->vendorDir) . '/web/midcom-static';
        if (!is_dir($target))
        {
            return;
        }

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child)
        {
            if (   $child->getType() == 'dir'
                && substr($child->getFileName(), 0, 1) !== '.')
            {
                $this->_link($child->getRealPath(), $target . '/' . $child->getFilename());
            }
        }
    }

    private function _link($target, $linkname)
    {
        if (is_link($linkname))
        {
            if (   realpath($linkname) !== $target
                && md5_file(realpath($linkname)) !== md5_file($target))
            {
                $this->io->write('Skipping <info>' . basename($target) . '</info>: Found Link in <info>' . dirname($linkname) . '</info> to <comment>' . realpath($linkname) . '</comment>');
            }
            return;
        }
        else if (is_file($linkname))
        {
            if (md5_file($linkname) !== md5_file($target))
            {
                $this->io->write('Skipping <info>' . basename($target) . '</info>: Found existing file in <comment>' . dirname($linkname) . '</comment>');
            }
            return;
        }
        if (!@symlink($target, $linkname))
        {
            throw new \Exception('could not link ' . $target . ' to ' . $linkname);
        }
        if ($this->io->isVerbose())
        {
            $this->io->write('Linked <info>' . $target . '</info> to <comment>' . $linkname . '</comment>');
        }
    }
}