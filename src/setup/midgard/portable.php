<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer\setup\midgard;

use midgard\portable\driver;
use midgard\portable\storage\connection;

/**
 * Setup for midgard-portable
 *
 * @package openpsa.installer
 */
class portable extends \openpsa\installer\setup\base
{
    public function prepare_connection()
    {
        if (   file_exists($this->_basepath . '/config/midgard-portable.inc.php')
            && $this->_ask('Use existing configuration file <info>midgard-portable.inc.php</info> ?', 'y', array('y', 'n')) === 'y')
        {
            include $this->_basepath . '/config/midgard-portable.inc.php';
            return;
        }
        $schema_dirs = array
        (
            $this->_basepath . '/var/schemas/',
        );

        if (file_exists($this->_basepath . '/config/'))
        {
            $schema_dirs[] = $this->_basepath . '/config/';
        }
        if (file_exists($this->_basepath . '/vendor/openpsa/midcom/config/'))
        {
            $schema_dirs[] = $this->_basepath . '/vendor/openpsa/midcom/config/';
        }

        $driver = new driver($schema_dirs, $this->_basepath . '/var', '');

        $db_config = array(
            'dbname' => $this->_config->database,
            'user' => $this->_config->dbuser,
            'password' => $this->_config->dbpass,
            'host' => 'localhost',
            'driver' => ( ($this->_config->dbtype == 'MySQL') ? "pdo_mysql" : "pdo_sqlite" )
        );

        connection::initialize($driver, $db_config);
    }
}
?>
