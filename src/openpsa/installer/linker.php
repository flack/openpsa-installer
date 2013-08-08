<?php
namespace openpsa\installer;

class linker
{
    private $_basepath;
    private $_io;

    public function __construct($basepath, $io)
    {
        $this->_basepath = $basepath;
        $this->_io = $io;
    }

    public function install($path)
    {
        $this->_install_statics($path);
        $this->_install_themes($path);
        $this->_install_schemas($path);
    }

    private function _install_themes($repo_dir)
    {
        $source = $repo_dir . '/themes';
        if (!is_dir($source))
        {
            return;
        }
        $target = $this->_basepath . '/web/midcom-static';
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
                $this->_link($relative_path, $target . '/' . $child->getFilename(), $child->getPathname());
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
        $target = $this->_basepath . '/web/midcom-static';
        $this->_prepare_dir($target);
        $prefix = strlen($repo_dir) - 1;

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child)
        {
            if (   $child->getType() == 'dir'
                && substr($child->getFileName(), 0, 1) !== '.')
            {
                $relative_path = '../../' . substr($child->getPathname(), $prefix);
                $this->_link($relative_path, $target . '/' . $child->getFilename(), $child->getPathname());
            }
        }
    }

    private function _prepare_dir($dir)
    {
        if (   !is_dir($dir)
            && !@mkdir($dir))
        {
            throw new \Exception('could not create ' . $dir);
        }
    }

    private function _install_schemas($repo_dir)
    {
        if (extension_loaded('midgard'))
        {
            $this->_io->write('<warning>Linking schemas is not yet supported on mgd1, please do this manually if necessary</warning>');
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

    public function link($target, $linkname)
    {
        $this->_link($target, $linkname);
    }

    private function _link($target, $linkname, $target_path = null)
    {
        if (null === $target_path)
        {
            $target_path = $target;
        }
        $target_path = realpath($target_path);

        if (is_link($linkname))
        {
            if (   realpath($linkname) !== $target_path
                && md5_file(realpath($linkname)) !== md5_file($target_path))
            {
                $this->_io->write('Skipping <info>' . basename($target) . '</info>: Found Link in <info>' . dirname($linkname) . '</info> to <comment>' . realpath($linkname) . '</comment>');
            }
            return;
        }
        else if (is_file($linkname))
        {
            if (md5_file($linkname) !== md5_file($target_path))
            {
                $this->_io->write('Skipping <info>' . basename($target) . '</info>: Found existing file in <comment>' . dirname($linkname) . '</comment>');
            }
            return;
        }
        if (!@symlink($target, $linkname))
        {
            throw new \Exception('could not link ' . $target . ' to ' . $linkname);
        }
        if ($this->_io->isVerbose())
        {
            $this->_io->write('Linked <info>' . $target . '</info> to <comment>' . $linkname . '</comment>');
        }
    }
}