<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer;
use Composer\IO\IOInterface;
use Composer\IO\ConsoleIO;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\DialogHelper;

/**
 * Sets up a mgd2 configuration and DB
 *
 * @package openpsa.installer
 */
class mgd2setup extends service
{
    protected $_config_name;

    protected $_sharedir = '/usr/share/midgard2';

    public $dbtype;

    public static function get($basepath)
    {
        $io = new ConsoleIO(new ArgvInput, new ConsoleOutput, new HelperSet(array(new DialogHelper)));
        return new self($basepath, $io);
    }

    public function run()
    {
        if (getenv('OPENPSA_SKIP_DB_CREATION'))
        {
            return;
        }
        $config = $this->_load_config();
        $this->_prepare_database($config);
    }

    protected function _load_default($key = null)
    {
        $defaults = array();
        $defaults_file = $this->_basepath . '/vendor/.openpsa_installer_defaults.php';
        if (file_exists($defaults_file))
        {
            $defaults = json_decode(file_get_contents($defaults_file), true);
        }

        if (null !== $key)
        {
            if (array_key_exists($key, $defaults))
            {
                return $defaults[$key];
            }
            return null;
        }
        return $defaults;
    }

    protected function _save_default($key, $value)
    {
        $defaults = $this->_load_default();
        $defaults_file = $this->_basepath . '/vendor/.openpsa_installer_defaults.php';
        if (file_exists($defaults_file))
        {
            unlink($defaults_file);
        }
        $defaults[$key] = $value;
        file_put_contents($defaults_file, json_encode($defaults));
    }

    protected function _load_config()
    {
        if (getenv('MIDCOM_MIDGARD_CONFIG_FILE'))
        {
            $config_file = getenv('MIDCOM_MIDGARD_CONFIG_FILE');
        }
        else
        {
            $config_file = $this->_basepath . "/config/midgard2.ini";
        }

        if (file_exists($config_file))
        {
            $this->_io->write('Using config file found at <info>' . $config_file . '</info>');
            $config = new \midgard_config();
            if (!$config->read_file_at_path($config_file))
            {
                throw new \Exception('Could not read config file ' . $config_file);
            }
        }
        else
        {
            $config = $this->_create_config($config_file);
        }

        return $config;
    }

    private function _prepare_database(\midgard_config $config)
    {
        $this->_io->write('Preparing storage <comment>(this may take a while)</comment>');
        $midgard = \midgard_connection::get_instance();
        $midgard->open_config($config);
        if (!$midgard->is_connected())
        {
            throw new \Exception("Failed to open config {$config->database}:" . $midgard->get_error_string());
        }
        if (!$config->create_blobdir())
        {
            throw new \Exception("Failed to create file attachment storage directory to {$config->blobdir}:" . $midgard->get_error_string());
        }

        // Create storage
        if (!\midgard_storage::create_base_storage())
        {
            if ($midgard->get_error_string() != 'MGD_ERR_OK')
            {
                throw new \Exception("Failed to create base database structures" . $midgard->get_error_string());
            }
        }

        $re = new \ReflectionExtension('midgard2');
        $classes = $re->getClasses();
        foreach ($classes as $refclass)
        {
            if (!$refclass->isSubclassOf('midgard_object'))
            {
                continue;
            }
            $type = $refclass->getName();

            \midgard_storage::create_class_storage($type);
            \midgard_storage::update_class_storage($type);
        }
        $this->_io->write('Storage created');
    }

    private function _get_db_type()
    {
        if (!empty($this->dbtype))
        {
            return $this->dbtype;
        }
        return $this->_io->ask('<question>DB type:</question> [<comment>MySQL</comment>, SQLite] ', 'MySQL');
    }

    private function _create_config($config_name)
    {
        if (file_exists($this->_basepath . '/vendor/openpsa/midcom/'))
        {
            //openpsa installed as dependency
            $openpsa_basedir = realpath($this->_basepath . '/vendor/openpsa/midcom/');
        }
        else
        {
            //openpsa installed as root package
            $openpsa_basedir = realpath($this->_basepath);
        }
        $project_name = basename($this->_basepath);
        $linker = new linker($this->_basepath, $this->_io);

        $this->_prepare_dir('config');
        $this->_prepare_dir('var');
        $this->_prepare_dir('var/cache');
        $this->_prepare_dir('var/rcs');
        $this->_prepare_dir('var/blobs');
        $this->_prepare_dir('var/log');

        $linker->link($openpsa_basedir . '/config/midgard_auth_types.xml', $this->_sharedir . '/midgard_auth_types.xml');
        $linker->link($openpsa_basedir . '/config/MidgardObjects.xml', $this->_sharedir . '/MidgardObjects.xml');

        // Create a config file
        $config = new \midgard_config();
        $config->dbtype = $this->_get_db_type();
        if ($config->dbtype == 'MySQL')
        {
            $config->dbuser = $this->_io->ask('<question>DB username:</question> [<comment>' . $project_name . '</comment>] ', $project_name);
            $config->dbpass = $this->_io->askAndHideAnswer('<question>DB password:</question> ');
            $config->database = $this->_io->ask('<question>DB name:</question> [<comment>' . $project_name . '</comment>] ', $project_name);
        }
        else if ($config->dbtype == 'SQLite')
        {
            $config->dbdir = $this->_basepath . '/var';
            $config->database = $project_name;
        }
        else
        {
            throw new \Exception('Unsupported DB type ' . $config->dbtype);
        }
        $config->blobdir = $this->_basepath . '/var/blobs';
        $config->sharedir = $this->_sharedir;
        $config->vardir = $this->_basepath . '/var';
        $config->cachedir = $this->_basepath . '/var/cache';
        $config->logfilename = $this->_basepath . '/var/log/midgard.log';
        $config->loglevel = 'warn';

        $target_path = getenv('HOME') . '/.midgard2/conf.d/' . $project_name;

        if (!$config->save_file($project_name, true))
        {
            throw new \Exception("Failed to save config file " . $target_path);
        }

        $this->_io->write("Configuration file <info>" . $target_path . "</info> created.");
        $linker->link($target_path, $this->_basepath . '/config/midgard2.ini');
        return $config;
    }
}
?>
