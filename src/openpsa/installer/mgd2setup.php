<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer;

/**
 * Simple installer class. Sets up a mgd2 configuration and DB
 *
 * @package openpsa.installer
 */
class mgd2setup
{
    protected $_io;

    protected $_basedir;

    protected $_config_name;

    protected $_sharedir = '/usr/share/midgard2';

    public function __construct($basedir, $io)
    {
        $this->_io = $io;
        $this->_basedir = $basedir;
    }

    protected function _load_default($key = null)
    {
        $defaults = array();
        $defaults_file = $this->_basedir . '/vendor/.openpsa_installer_defaults.php';
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
        $defaults_file = $this->_basedir . '/vendor/.openpsa_installer_defaults.php';
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
            $config_file = $this->_basedir . "/config/midgard2.ini";
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

    public function run()
    {
        $config = $this->_load_config();
        $this->_prepare_database($config);
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

    private function _create_config($config_name)
    {
        $openpsa_basedir = realpath($this->_basedir . '/vendor/openpsa/midcom/');
        $project_name = basename($this->_basedir);
        $linker = new linker($this->_basedir, $this->_io);

        self::_prepare_dir('config');
        self::_prepare_dir('var');
        self::_prepare_dir('var/cache');
        self::_prepare_dir('var/rcs');
        self::_prepare_dir('var/blobs');
        self::_prepare_dir('var/log');

        $linker->link($openpsa_basedir . '/config/midgard_auth_types.xml', $this->_sharedir . '/midgard_auth_types.xml');
        $linker->link($openpsa_basedir . '/config/MidgardObjects.xml', $this->_sharedir . '/MidgardObjects.xml');

        // Create a config file
        $config = new \midgard_config();
        $config->dbtype = 'MySQL';
        $config->dbuser = $this->_io->ask('<question>DB username:</question> [<comment>' . $project_name . '</comment>] ', $project_name);
        $config->dbpass = $this->_io->askAndHideAnswer('<question>DB password:</question> ');

        $config->database = $this->_io->ask('<question>DB name:</question> [<comment>' . $project_name . '</comment>] ', $project_name);
        $config->blobdir = $this->_basedir . '/var/blobs';
        $config->sharedir = $this->_sharedir;
        $config->vardir = $this->_basedir . '/var';
        $config->cachedir = $this->_basedir . '/var/cache';
        $config->logfilename = $this->_basedir . '/var/log/midgard.log';
        $config->loglevel = 'warn';

        $target_path = getenv('HOME') . '/.midgard2/conf.d/' . $project_name;
        if (!$config->save_file($project_name, true))
        {
            throw new \Exception("Failed to save config file " . $target_path);
        }

        $this->_io->write("Configuration file <info>" . $target_path . "</info> created.");
        $linker->link($target_path, $this->_basedir . '/config/midgard2.ini');
        return $config;
    }
}
?>
