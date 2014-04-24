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
        $schema_dirs = array
        (
            $this->_basepath . '/schemas/',
            $this->_basepath . '/config/'
        );
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
