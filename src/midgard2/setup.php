<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer\midgard2;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use openpsa\installer\installer;

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
     * @var \openpsa\installer\setup
     */
    protected $_setup;

    public static function install($basepath, $dbtype = 'MySQL')
    {
        installer::setup_project_directory($basepath);
        $app = new Application;
        $app->add(new self);

        $args = [
            'command' => 'midgard2:setup',
            '--dbtype' => $dbtype
        ];
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

    private function get_setup_strategy()
    {
        $helperset = $this->getHelperSet();
        return new \openpsa\installer\setup($this->_input, $this->_output, $this->_basepath, $helperset);
    }

    protected function _initialize(InputInterface $input, OutputInterface $output)
    {
        if (empty($this->_basepath)) {
            $this->_basepath = realpath('./');
        }

        $this->_output = $output;
        $this->_input = $input;

        $this->_setup = $this->get_setup_strategy();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_initialize($input, $output);

        $config = $this->_setup->prepare_config();
        $this->_setup->prepare_storage();
    }
}
