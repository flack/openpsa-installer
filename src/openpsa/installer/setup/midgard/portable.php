<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer\setup\midgard;

use openpsa\installer\installer;
use openpsa\installer\linker;
use midgard\introspection\helper;
use Composer\Util\Filesystem;
use Composer\IO\ConsoleIO;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

use midgard\portable\driver;
use midgard\portable\storage\connection;

/**
 * Setup for midgard-portable
 *
 * @package openpsa.installer
 */
class portable extends \openpsa\installer\setup\base
{
    public function load_config()
    {
        $schema_dirs = array
        (
            $this->_basepath . '/schemas/',
            $this->_basepath . '/config/'
        );
        $driver = new driver($schema_dirs, $this->_basepath . '/var', '');

        $config = parent::load_config();

        $db_config = array(
            'dbname' => $config->database,
            'user' => $config->dbuser,
            'password' => $config->dbpass,
            'host' => 'localhost',
            'driver' => ( ($config->dbtype == 'MySQL') ? "pdo_mysql" : "pdo_sqlite" )
        );

        connection::initialize($driver, $db_config);

        return $config;
    }

}
?>
