<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer\setup;

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


/**
 * Setup for mgd2
 *
 * @package openpsa.installer
 */
class midgard2 extends \openpsa\installer\setup\base
{

    public function prepare_connection()
    {
        $midgard = \midgard_connection::get_instance();

        $midgard->open_config($this->_config);
        if (!$midgard->is_connected())
        {
            throw new \Exception("Failed to open config {$this->_config->database}:" . $midgard->get_error_string());
        }
    }
}
?>
