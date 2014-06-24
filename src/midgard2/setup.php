<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer\midgard2;

use openpsa\installer\installer;
use midgard\introspection\helper;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Sets up a mgd2 configuration and DB
 *
 * @package openpsa.installer
 */
class setup extends Command
{
    /**
     * @var InputInterface
     */
    protected $_input;

    /**
     * @var OutputInterface
     */
    protected $_output;

    /**
     * The share dir
     *
     * @var string
     */
    protected $_sharedir;

    /**
     * The root package path
     *
     * @var string
     */
    protected $_basepath;

    /**
     * the setup class specific to midgard2 or midgard-portable
     *
     * @var interfaces_setup
     */
    protected $_setup;

    public static function install($basepath, $dbtype = 'MySQL')
    {
        installer::setup_project_directory($basepath);
        $app = new Application;
        $app->add(new self);

        $args = array
        (
            'command' => 'midgard2:setup',
            '--dbtype' => $dbtype
        );
        $command = $app->find('midgard2:setup');
        $command->set_basepath($basepath);

        return $command->run(new ArrayInput($args), new StreamOutput(fopen('php://stdout', 'w')));
    }

    public function set_basepath($basepath)
    {
        $this->_basepath = $basepath;
    }

    protected function configure()
    {
        $this->setName('midgard2:setup')
            ->setDescription('Prepare Midgard2 database and project directory')
            ->addArgument('config', InputArgument::OPTIONAL, 'Full path to Midgard2 config file')
            ->addOption('dbtype', null, InputOption::VALUE_REQUIRED, 'DB type', 'MySQL');
    }

    private function _get_setup_strategy()
    {
        if (extension_loaded('midgard'))
        {
            throw new \Exception('Midgard1 is not supported. Please use datagard instead.');
        }

        if ($this->_input->hasOption('dbtype'))
        {
            $dbtype = $this->_input->getOption('dbtype');
        }
        $helperset = $this->getHelperSet();
        if (empty($dbtype))
        {
            $dialog = $helperset->get('dialog');
            $dbtype = $dialog->ask($this->_output, '<question>DB type:</question>', 'MySQL', array('MySQL', 'SQLite'));
        }

        if (extension_loaded('midgard2'))
        {
            return new \openpsa\installer\setup\midgard2($this->_input, $this->_output, $this->_basepath, $this->_sharedir, $dbtype, $helperset);
        }
        $this->_output->writeln("<info>Running setup for midgard-portable</info>");
        return new \openpsa\installer\setup\midgard\portable($this->_input, $this->_output, $this->_basepath, $this->_sharedir, $dbtype, $helperset);
    }

    protected function _initialize(InputInterface $input, OutputInterface $output)
    {
        if (empty($this->_basepath))
        {
            $this->_basepath = realpath('./');
        }
        $this->_sharedir = '/usr/share/midgard2';

        $this->_output = $output;
        $this->_input = $input;

        $this->_setup = $this->_get_setup_strategy();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_initialize($input, $output);

        $config = $this->_setup->prepare_config();
        $this->_setup->prepare_storage();
    }
}
?>
