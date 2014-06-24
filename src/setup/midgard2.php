<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer\setup;


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
