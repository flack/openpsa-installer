<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer;

use Composer\Util\Filesystem;
use Composer\IO\ConsoleIO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Sets up a mgd2 configuration and DB
 *
 * @package openpsa.installer
 */
class mgd2setup extends Command
{
    /**
     * @var string
     */
    protected $_config_name;

    /**
     * @var Symfony\Component\Console\Output\InputInterface
     */
    protected $_input;

    /**
     * @var Symfony\Component\Console\Output\OutputInterface
     */
    protected $_output;

    protected $_sharedir = '/usr/share/midgard2';

    /**
     * @var string
     */
    protected $_dbtype;

    /**
     * The root package path
     *
     * @var string
     */
    protected $_basepath;

    /**
     * @var Composer\Util\Filesystem
     */
    protected $_fs;

    protected function configure()
    {
        $this->setName('mgd2:setup')
            ->setDescription('Prepare Midgard2 database and project directory')
            ->addArgument('config', InputArgument::OPTIONAL, 'Full path to Midgard2 config file')
            ->addOption('dbtype', null, InputOption::VALUE_REQUIRED, 'DB type', 'MySQL');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_basepath = realpath('./');
        $this->_fs = new Filesystem;
        $this->_output = $output;
        $this->_input = $input;
        $config = $this->_load_config();
        $this->_prepare_database($config);
    }

    protected function _load_config()
    {
        $config_file = $this->_input->getArgument('config');
        if (!$config_file)
        {
            $config_file = $this->_basepath . "/config/midgard2.ini";
        }

        if (file_exists($config_file))
        {
            $this->_output->writeln('Using config file found at <info>' . $config_file . '</info>');
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
        $this->_output->writeln('Preparing storage <comment>(this may take a while)</comment>');
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

        $re = new \ReflectionExtension('midgard2');
        $classes = $re->getClasses();
        $types = array();
        foreach ($classes as $refclass)
        {
            if (!$refclass->isSubclassOf('midgard_object'))
            {
                continue;
            }
            $types[] = $refclass->getName();
        }

        $progress = $this->getHelperSet()->get('progress');
        $progress->start($this->_output, count($types) + 1);

        // Create storage
        if (!\midgard_storage::create_base_storage())
        {
            if ($midgard->get_error_string() != 'MGD_ERR_OK')
            {
                throw new \Exception("Failed to create base database structures" . $midgard->get_error_string());
            }
        }
        $progress->advance();

        foreach ($types as $type)
        {
            \midgard_storage::create_class_storage($type);
            \midgard_storage::update_class_storage($type);
            $progress->advance();
        }
        $progress->finish();

        $this->_output->writeln('Storage created');
    }

    private function _get_db_type()
    {
        $this->dbtype = $this->_input->getOption('dbtype');
        if (empty($this->dbtype))
        {
            $this->dbtype = $this->_ask('DB type:', 'MySQL', array('MySQL', 'SQLite'));
        }
        return $this->dbtype;
    }

    protected function _ask($question, $default, array $options = null)
    {
        $dialog = $this->getHelperSet()->get('dialog');
        return $dialog->ask($this->_output, '<question>' . $question . '</question>', $default, $options);
    }

    protected function _ask_hidden($question)
    {
        $dialog = $this->getHelperSet()->get('dialog');
        return $dialog->askHiddenResponse($this->_output, '<question>' . $question . '</question>', false);
    }

    private function _create_config($config_name)
    {
        $project_name = basename($this->_basepath);

        // Create a config file
        $config = new \midgard_config();
        $config->dbtype = $this->_get_db_type();
        if ($config->dbtype == 'MySQL')
        {
            $config->dbuser = $this->_ask('DB username:', $project_name);
            $config->dbpass = $this->_ask_hidden('DB password:');
            $config->database = $this->_ask('DB name:', $project_name);
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

        try
        {
            $linker = new linker($this->_basepath, new ConsoleIO($this->_input, $this->_output, $this->getHelperSet()));
            $linker->link($target_path, $this->_basepath . '/config/midgard2.ini');
            $this->_output->writeln("Configuration file <info>" . $target_path . "</info> created.");
        }
        catch (\Exception $e)
        {
            // For some strange reason, this happens in Travis. But the config was created successfully
            // (according to save_file()'s return value anyways), and the link is not essential,
            // so we print an error and continue
            $this->_output->writeln("Configuration file <info>" . $project_name . "</info> was successfully created, but could not be linked: <error>" . $e->getMessage() . "</error>");
        }
        return $config;
    }
}
?>
