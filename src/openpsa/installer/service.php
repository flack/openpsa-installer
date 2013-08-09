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
 * Installer service base class
 *
 * @package openpsa.installer
 */
abstract class service
{
    /**
     * The root package path
     *
     * @var string
     */
    protected $_basepath;

    /**
     * Composer IO interface
     *
     * @var Composer\IO\IOInterface
     */
    protected $_io;

    /**
     * Default constructor
     *
     * @param string $basepath The root package path
     * @param IOInterface $io Composer IO interface
     */
    public function __construct($basepath, IOInterface $io)
    {
        $this->_basepath = $basepath;
        $this->_io = $io;
    }

    protected function _prepare_dir($dir)
    {
        if (   !is_dir($dir)
            && !@mkdir($dir))
        {
            $error = error_get_last();
            throw new \Exception('could not create ' . $dir . ': ' . $error['message']);
        }
    }
}