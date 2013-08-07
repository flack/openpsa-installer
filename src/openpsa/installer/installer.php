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
        $this->_install_statics($this->getPackageBasePath($target));
        $this->_install_themes($this->getPackageBasePath($target));
        if ($this->_type !== 'midcom-extras')
        {
            $this->_install_schemas($this->getPackageBasePath($target));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);
        $this->_install_statics($this->getPackageBasePath($package));
        $this->_install_themes($this->getPackageBasePath($package));
        if ($this->_type !== 'midcom-extras')
        {
            $this->_install_schemas($this->getPackageBasePath($package));
        }
    }

    private function _install_themes($repo_dir)
    {
        $source = $repo_dir . '/themes';
        if (!is_dir($source))
        {
            return;
        }
        $target = dirname($this->vendorDir) . '/web/midcom-static';
        $this->_prepare_dir($target);
        $prefix = strlen($repo_dir) - 1;

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child)
        {
            if (   $child->getType() == 'dir'
                && substr($child->getFileName(), 0, 1) !== '.'
                   && is_dir($child->getPathname()) . '/static')
            {
                $relative_path = '../../' . substr($child->getPathname() . '/static', $prefix);
                $this->_link($relative_path, $target . '/' . $child->getFilename());
            }
        }
    }

    private function _install_statics($repo_dir)
    {
        $source = $repo_dir . '/static';
        if (!is_dir($source))
        {
            return;
        }
        $target = dirname($this->vendorDir) . '/web/midcom-static';
        $this->_prepare_dir($target);
        $prefix = strlen($repo_dir) - 1;

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child)
        {
            if (   $child->getType() == 'dir'
                && substr($child->getFileName(), 0, 1) !== '.')
            {
                $relative_path = '../../' . substr($child->getPathname(), $prefix);
                $this->_link($relative_path, $target . '/' . $child->getFilename());
            }
        }
    }

    private function _prepare_dir($dir)
    {
        if (   !is_dir('./' . $dir)
            && !mkdir('./' . $dir))
        {
            throw new \Exception('could not create ' . $dir);
        }
    }

    private function _install_schemas($repo_dir)
    {
        if (extension_loaded('midgard'))
        {
            $this->io->write('<warning>Linking schemas is not yet supported on mgd1, please do this manually if necessary</warning>');
            return;
        }
        if (!is_dir($repo_dir . '/schemas'))
        {
            return;
        }

        $basepath = '/usr/share/midgard2/schema/';

        $iterator = new \DirectoryIterator($repo_dir . '/schemas');
        foreach ($iterator as $child)
        {
            if (   $child->getType() == 'file'
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