<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer;

use midgard\portable\driver;
use midgard\portable\storage\connection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Composer\IO\ConsoleIO;
use Symfony\Component\Console\Question\ChoiceQuestion;
use midgard\portable\command\schema;

/**
 * Setup for midgard-portable
 *
 * @package openpsa.installer
 */
class setup
{
    /**
     *
     * @var InputInterface
     */
    protected $_input;

    /**
     *
     * @var OutputInterface
     */
    protected $_output;

    /**
     * The root package path
     *
     * @var string
     */
    protected $_basepath;

    protected $_helperset;

    protected $_config = null;

    public function __construct(InputInterface $input, OutputInterface $output, $basepath, $helperset)
    {
        $this->_input = $input;
        $this->_output = $output;
        $this->_basepath = $basepath;
        $this->_helperset = $helperset;
    }

    protected function _determine_config_path()
    {
        $config_file = $this->_input->getArgument('config');
        if (   $config_file
            && (   !file_exists($config_file)
                || !is_file($config_file))) {
            //The working theory here is that input was a filename, rather than a path
            $prefix = getenv('HOME') . '/.midgard2/conf.d/';
            if (!file_exists($prefix . $config_file)) {
                $this->_output->writeln("Configuration file <info>" . $config_file . "</info> not found.");
                return false;
            }
            $config_file = $prefix . $config_file;
        } else {
            $config_file = $this->_basepath . "/config/midgard2.ini";
        }
        return $config_file;
    }

    public function prepare_config()
    {
        $this->_config = $this->load_config();
        return $this->_config;
    }

    public function load_config()
    {
        $config_file = $this->_determine_config_path();
        // no config so far...
        if (!file_exists($config_file)) {
            return $this->create_config();
        }

        $this->_output->writeln('Using config file found at <info>' . $config_file . '</info>');

        $config = new \midgard_config;
        if (!$config->read_file_at_path($config_file)) {
            throw new \Exception('Could not read config file ' . $config_file);
        }
        return $config;
    }

    public function create_config()
    {
        $project_name = $this->_input->getArgument('config');
        if (!$project_name) {
            $project_name = basename($this->_basepath);
            // unittests
            if ($project_name == "__output") {
                $project_name = basename(dirname(dirname($this->_basepath))) . "_test";
            }
        }

        if ($this->_input->hasOption('dbtype')) {
            $dbtype = $this->_input->getOption('dbtype');
        }
        if (empty($dbtype)) {
            $dialog = $this->_helperset->get('question');
            $question = new ChoiceQuestion('<question>DB type:</question>', array('MySQL', 'SQLite'), 0);
            $dbtype = $dialog->ask($this->_input, $this->_output, $question);
        }

        // Create a config file
        $config = new \midgard_config();
        $config->dbtype = $dbtype;

        if ($config->dbtype == 'MySQL') {
            $config->dbuser = $this->_ask('DB username:', $project_name);
            $config->dbpass = $this->_ask_hidden('DB password:');
            $config->database = $this->_ask('DB name:', $project_name);
        } elseif ($config->dbtype == 'SQLite') {
            $config->dbdir = $this->_basepath . '/var';
            $config->database = $project_name;
        } else {
            throw new \Exception('Unsupported DB type ' . $config->dbtype);
        }

        $config->blobdir = $this->_basepath . '/var/blobs';
        $config->sharedir = $this->_basepath . '/var/schemas';
        $config->vardir = $this->_basepath . '/var';
        $config->cachedir = $this->_basepath . '/var/cache';
        $config->logfilename = $this->_basepath . '/var/log/midgard.log';
        $config->loglevel = 'warn';

        $target_path = getenv('HOME') . '/.midgard2/conf.d/' . $project_name;

        if (!$config->save_file($project_name, true)) {
            throw new \Exception("Failed to save config file " . $target_path);
        }

        try {
            $linker = new linker($this->_basepath, new ConsoleIO($this->_input, $this->_output, $this->_helperset));
            $linker->link($target_path, $this->_basepath . '/config/midgard2.ini');
            $this->_output->writeln("Configuration file <info>" . $target_path . "</info> created.");
        } catch (\Exception $e) {
            // For some strange reason, this happens in Travis. But the config was created successfully
            // (according to save_file()'s return value anyways), and the link is not essential,
            // so we print an error and continue
            $this->_output->writeln("Configuration file <info>" . $project_name . "</info> was successfully created, but could not be linked: <error>" . $e->getMessage() . "</error>");
        }
        return $config;
    }

    public function prepare_connection($autostart = true)
    {
        connection::set_autostart($autostart);
        if (   file_exists($this->_basepath . '/config/midgard-portable.inc.php')
            && $this->_ask('Use existing configuration file <info>midgard-portable.inc.php</info> ?', true)) {
            include $this->_basepath . '/config/midgard-portable.inc.php';
            return;
        }
        $schema_dirs = array(
            $this->_basepath . '/var/schemas/',
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

    public function prepare_storage()
    {
        if (is_null($this->_config)) {
            throw new \Exception("No config loaded");
        }

        $this->prepare_connection(false);

        $midgard = \midgard_connection::get_instance();
        $this->_output->writeln('Preparing <info>' . $this->_config->dbtype . '</info> storage <comment>(this may take a while)</comment>');

        if (!$this->_config->create_blobdir()) {
            throw new \Exception("Failed to create file attachment storage directory to {$this->_config->blobdir}:" . $midgard->get_error_string());
        }

        $schema = new schema();
        $schema->setHelperSet($this->_helperset);
        $schema->connected = true;
        $schema->run($this->_input, $this->_output);

        $helper = new \midgard\introspection\helper;
        $types = $helper->get_all_schemanames();

        // no idea why this has to be listed explicitly...
        $types[] = 'MidgardRepligard';

        $progress = new ProgressBar($this->_output);
        $progress->start(count($types) + 2);

        // create storage
        if (!\midgard_storage::create_base_storage()) {
            if ($midgard->get_error_string() != 'MGD_ERR_OK') {
                throw new \Exception("Failed to create base database structures" . $midgard->get_error_string());
            }
        }
        $progress->advance();

        foreach ($types as $type) {
            if (!\midgard_storage::class_storage_exists($type)) {
                \midgard_storage::create_class_storage($type);
            }
            // for some reason, create misses some fields under midgard2, so we call update unconditionally
            \midgard_storage::update_class_storage($type);
            $progress->advance();
        }
        $progress->finish();

        $this->_output->writeln("\nStorage created");
    }

    protected function _ask($question, $default)
    {
        $dialog = $this->_helperset->get('question');
        if (is_bool($default)) {
            $question = new ConfirmationQuestion('<question>' . $question . '</question>', $default);
        } else {
            $question = new Question('<question>' . $question . '</question>', $default);
        }
        return $dialog->ask($this->_input, $this->_output, $question);
    }

    protected function _ask_hidden($question)
    {
        $dialog = $this->_helperset->get('question');
        $question = new Question('<question>' . $question . '</question>');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        return $dialog->ask($this->_input, $this->_output, $question);
    }
}
