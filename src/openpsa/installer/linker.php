<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer;
use Composer\IO\IOInterface;

/**
 * Link management service
 *
 * @package openpsa.installer
 */
class linker extends service
{
    private $_themes_dir = '/themes';
    private $_schemas_dir = '/schemas';
    private $_static_dir = '/static';
    private $_schema_location = '/usr/share/midgard2/schema/';

    private $_readonly_behavior;

    /**
     * Package installation routine
     *
     * @param string $path The package path
     */
    public function install($path)
    {
        $this->_install_statics($path);
        $this->_install_themes($path);
        $this->_install_schemas($path);
    }

    /**
     * Package uninstallation routine
     *
     * @param string $path The package path
     */
    public function uninstall($path)
    {
        $this->_uninstall_statics($path);
        $this->_uninstall_themes($path);
        $this->_uninstall_schemas($path);
    }

    public function set_schema_location($path)
    {
        $this->_schema_location = $path;
    }

    /**
     * Direct access to link functionality
     *
     * @param string $target
     * @param string $linkname
     */
    public function link($target, $linkname)
    {
        $this->_link($target, $linkname);
    }

    private function _uninstall_schemas($repo_dir)
    {
        if (extension_loaded('midgard'))
        {
            $this->_io->write('<warning>Unlinking schemas is not yet supported on mgd1, please do this manually if necessary</warning>');
            return;
        }
        $source = $repo_dir . $this->_schemas_dir;
        if (!is_dir($source))
        {
            return;
        }

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child)
        {
            if (   $child->getType() == 'file'
                && substr($child->getFileName(), 0, 1) !== '.')
            {
                $this->_unlink($this->_schema_location . $child->getFilename());
            }
        }
    }

    private function _uninstall_themes($repo_dir)
    {
        $source = $repo_dir . $this->_themes_dir;
        $target = $this->_basepath . '/web/midcom-static';

        if (   !is_dir($source)
            || !is_dir($target))
        {
            return;
        }

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child)
        {
            if (   $child->getType() == 'dir'
                && substr($child->getFileName(), 0, 1) !== '.'
                && is_dir($child->getPathname()) . '/static')
            {
                $this->_unlink($target . '/' . $child->getFilename());
            }
        }
    }

    private function _uninstall_statics($repo_dir)
    {
        $source = $repo_dir . $this->_static_dir;
        $target = $this->_basepath . '/web/midcom-static';

        if (   !is_dir($source)
            || !is_dir($target))
        {
            return;
        }

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child)
        {
            if (   $child->getType() == 'dir'
                && substr($child->getFileName(), 0, 1) !== '.')
            {
                $this->_unlink($target . '/' . $child->getFilename());
            }
        }
    }

    private function _unlink($linkname)
    {
        if (is_link($linkname))
        {
            $this->_io->write('Removing link <info>' . $linkname . '</info>');
            @unlink($linkname);
        }
    }

    private function _install_schemas($repo_dir)
    {
        if (!extension_loaded('midgard2'))
        {
            $this->_io->write('<warning>Linking schemas is only supported on mgd2 right now, please do this manually if necessary</warning>');
            return;
        }
        $source = $repo_dir . $this->_schemas_dir;
        if (!is_dir($source))
        {
            return;
        }

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child)
        {
            if (   $child->getType() == 'file'
                && substr($child->getFileName(), 0, 1) !== '.'
                && substr($child->getFilename(), -4) === '.xml')
            {
                $this->_link($child->getRealPath(), $this->_schema_location . $child->getFilename());
            }
        }
    }

    private function _install_themes($repo_dir)
    {
        $source = $repo_dir . $this->_themes_dir;
        if (!is_dir($source))
        {
            return;
        }
        $this->_prepare_static_dir();
        $target = $this->_basepath . '/web/midcom-static';

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child)
        {
            if (   $child->getType() == 'dir'
                && substr($child->getFileName(), 0, 1) !== '.'
                && is_dir($child->getPathname() . '/static'))
            {
                $absolute_path = $child->getPathname() . '/static';
                $relative_path = $this->_get_relative_path($absolute_path);
                $this->_link($relative_path, $target . '/' . $child->getFilename(), $absolute_path);
            }
        }
    }

    private function _install_statics($repo_dir)
    {
        $source = $repo_dir . $this->_static_dir;
        if (!is_dir($source))
        {
            return;
        }
        $this->_prepare_static_dir();
        $target = $this->_basepath . '/web/midcom-static';

        $iterator = new \DirectoryIterator($source);
        foreach ($iterator as $child)
        {
            if (   $child->getType() == 'dir'
                && substr($child->getFileName(), 0, 1) !== '.')
            {
                $absolute_path = $child->getPathname();
                $relative_path = $this->_get_relative_path($absolute_path);
                $this->_link($relative_path, $target . '/' . $child->getFilename(), $absolute_path);
            }
        }
    }

    private function _prepare_static_dir()
    {
        $this->_prepare_dir($this->_basepath . '/web');
        $this->_prepare_dir($this->_basepath . '/web/midcom-static');
    }

    private function _get_relative_path($absolute_path)
    {
        return '../../' . substr($absolute_path, strlen($this->_basepath) + 1);
    }

    private function _link($target, $linkname, $target_path = null)
    {
        if (null === $target_path)
        {
            $target_path = $target;
        }
        $target_path = realpath($target_path);

        if (!file_exists($target_path))
        {
            throw new \Exception('Cannot link to nonexistent path ' . $target);
        }

        if (is_link($linkname))
        {
            if (!file_exists(realpath($linkname)))
            {
                $this->_io->write('Link in <info>' . basename($target) . '</info> points to nonexistant path, removing');
                @unlink($linkname);
            }
            else
            {
                if (   realpath($linkname) !== $target_path
                    && md5_file(realpath($linkname)) !== md5_file($target_path))
                {
                    $this->_io->write('Skipping <info>' . basename($target) . '</info>: Found Link in <info>' . dirname($linkname) . '</info> to <comment>' . realpath($linkname) . '</comment>');
                }
                return;
            }
        }
        else if (is_file($linkname))
        {
            if (md5_file($linkname) !== md5_file($target_path))
            {
                $this->_io->write('Skipping <info>' . basename($target) . '</info>: Found existing file in <comment>' . dirname($linkname) . '</comment>');
            }
            return;
        }

        if (!is_writeable(dirname($linkname)))
        {
            if ($this->_readonly_behavior === null)
            {
                $this->_io->write('Directory <info>' . dirname($linkname) . '</info> is not writeable.');
                $reply = $this->_io->ask('<question>Please choose:</question> [<comment>(S)udo</comment>, (I)gnore, (A)bort]', 'S');
                $this->_readonly_behavior = strtolower(trim($reply));
            }
            switch ($this->_readonly_behavior)
            {
                case 'a':
                    throw new \Exception('Aborted by user command');
                case 'i':
                    $this->_io->write('<info>Skipped linking ' . basename($linkname) . ' to ' . dirname($linkname) . '</info>');
                    return;
                case '':
                case 's':
                    exec('sudo ln -s ' . escapeshellarg($target) . ' ' . escapeshellarg($linkname), $output, $return);
                    if ($return !== 0)
                    {
                        throw new \Exception('Failed to link ' . basename($linkname) . ' to ' . dirname($linkname));
                    }
                    break;
                default:
                    throw new \Exception('Invalid input');
            }
        }
        else
        {
            if (!@symlink($target, $linkname))
            {
                $error = error_get_last();
                throw new \Exception('could not link ' . $target . ' to ' . $linkname . ': ' . $error['message']);
            }
        }
        if ($this->_io->isVerbose())
        {
            $this->_io->write('Linked <info>' . $target . '</info> to <comment>' . $linkname . '</comment>');
        }
    }
}
