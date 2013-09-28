<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

namespace openpsa\installer\midgard2;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Converts a mgd1 DB to mgd2
 *
 * Be advised that this does not multiple languages or sitegroups into account.
 *
 * @package openpsa.installer
 */
class convert extends setup
{
    protected $_dbtype = 'MySQL';

    public $multilang_tables = array
    (
        'topic' => array('title', 'extra', 'description'),
        'article' => array('title', 'abstract', 'content', 'url'),
        'element' => array('value'),
        'net_nemein_redirector_tinyurl' => array('title', 'description'),
        'org_openpsa_products_product_group' => array('title', 'description'),
        'org_openpsa_products_product' => array('title', 'description'),
        'pageelement' => array('value'),
        'page' => array('title', 'content', 'author', 'owner'),
        'snippet' => array('code', 'doc'),
    );

    protected function configure()
    {
        $this->setName('midgard2:convert')
            ->setDescription('Convert a Midgard 1 MySQL database to Midgard 2')
            ->addArgument('config', InputArgument::REQUIRED, 'Name of Midgard2 config file')
            ->addOption('authtype', null, InputOption::VALUE_REQUIRED, 'Password storage type', 'SHA256')
            ->addOption('skip-storage', null, InputOption::VALUE_NONE, 'Skip Midgard storage upgrade');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->_initialize($input, $output);

        if (!$this->_input->getOption('skip-storage'))
        {
            $this->_prepare_storage();
        }
        else
        {
            $midgard = \midgard_connection::get_instance();
            $midgard->open_config($this->_config);
            if (!$midgard->is_connected())
            {
                throw new \Exception("Failed to open config {$this->_config->database}:" . $midgard->get_error_string());
            }
        }
        $this->_convert_tables();
        $this->_migrate_accounts();
        $this->_update_at_entries();
    }

    private function _convert_tables()
    {
        $this->_output->writeln("\n<info>Copying data from multilang tables</info>");
        $db = mysqli_connect($this->_config->host, $this->_config->dbuser, $this->_config->dbpass, $this->_config->database);
        if (mysqli_connect_errno())
        {
            throw new \RuntimeException(mysqli_connect_error());
        }

        mysqli_set_charset($db, 'utf8');

        $this->_run_query('SET NAMES utf8', $db);

        foreach ($this->multilang_tables as $table => $fields)
        {
            if (   !$this->_verify_table($db, $table)
                || !$this->_verify_table($db, $table . '_i'))
            {
                continue;
            }

            $stmt = 'UPDATE ' . $table . ', ' . $table . '_i SET ';
            $columns = array();
            foreach ($fields as $field)
            {
                $columns[] = $table . '.' . $field . ' = ' . $table . '_i.' . $field;
            }
            $stmt .= implode(', ', $columns);

            $stmt .= ' WHERE ' . $table . '_i.lang = 0 AND ' . $table . '_i.sid = ' . $table . '.id';

            $this->_run_query($stmt, $db);
        }
        //fix changed snippet parent property
        $this->_run_query('UPDATE snippet SET snippetdir = up', $db);
    }

    private function _verify_table($db, $table)
    {
        $result = mysqli_query($db, 'SHOW TABLES LIKE "' . $table . '"');
        if (!$result)
        {
            throw new \RuntimeException(mysqli_error($db));
        }
        if (mysqli_num_rows($result) == 0)
        {
            $this->_output->writeln(' - Table <info>' . $this->_config->database . '.' . $table . '</info> could not be found, skipping');
            return false;
        }
        return true;
    }

    private function _run_query($stmt, $db)
    {
        if (!mysqli_query($db, $stmt))
        {
            throw new \RuntimeException(mysqli_error($db));
        }
    }

    /**
     * Update all AT entries to host 0 (since mgd2 doesn't support hosts)
     */
    private function _update_at_entries()
    {
        $this->_output->write("\n<info>Updating AT entries</info>");
        $qb = \midcom_services_at_entry_db::new_query_builder();
        $results = $qb->execute();
        foreach ($results as $result)
        {
            $result->host = 0;
            $result->update();
        }
        $this->_output->writeln("  <comment>... Done.</comment>");
    }

    /**
     * Migrate accounts to new system
     * You'll have to specify authtype manually if you don't want the default one
     */
    private function _migrate_accounts()
    {
        $this->_output->writeln("\n<info>Migrating user accounts</info>");
        if (empty($GLOBALS['midcom_config_local']))
        {
            $GLOBALS['midcom_config_local'] = array();
        }
        $GLOBALS['midcom_config_local']['person_class'] = 'openpsa_person';
        $GLOBALS['midcom_config_local']['auth_type'] = $this->_input->getOption('authtype');
        if (!defined('OPENPSA2_PREFIX'))
        {
            define('OPENPSA2_PREFIX', '/');
        }
        $defaults = array
        (
            'SERVER_PORT' => '80',
            'SERVER_NAME' => 'localhost',
            'HTTP_HOST' => 'localhost',
            'REQUEST_URI' => '/midgard2-convert'
        );
        $_SERVER = array_merge($defaults, $_SERVER);

        \midcom::init();

        $qb = new \midgard_query_builder(\midcom::get('config')->get('person_class'));
        $qb->add_constraint('username', '<>', '');
        $results = $qb->execute();

        foreach ($results as $person)
        {
            if (!$this->_migrate_account($person))
            {
                $this->_output->writeln('   <error>Account for <info>' . $person->firstname . ' ' . $person->lastname . "</info> couldn't be migrated!</error>");
            }
        }
        $this->_output->writeln("  Done.");
    }

    private function _migrate_account($person)
    {
        $user = new \midgard_user();
        $user->authtype = \midcom::get('config')->get('auth_type');
        $db_password = $person->password;

        $this->_output->writeln("\n - Processing user <info>" . $person->username . "</info>");

        if (substr($person->password, 0, 2) == '**')
        {
            $db_password = \midcom_connection::prepare_password(substr($db_password, 2));
        }
        else
        {
            if ($user->authtype !== 'Legacy')
            {
                $this->_output->writeln("   Legacy password detected, resetting to <comment>'password'</comment>, please change ASAP");
                $db_password = \midcom_connection::prepare_password('password');
            }
        }

        $user->password = $db_password;
        $user->login = $person->username;

        if (\midcom::get('config')->get('person_class') != 'midgard_person')
        {
            $mgd_person = new \midgard_person($person->guid);
        }
        else
        {
            $mgd_person = $person;
        }

        $user->set_person($mgd_person);
        $user->active = true;

        if ($this->_is_admin($person))
        {
            $user->usertype = 2;
            $this->_output->writeln('   <comment>Setting admin flag</comment>');
        }


        try
        {
            $user->create();
        }
        catch (\midgard_error_exception $e)
        {
            return false;
        }
        return true;
    }

    private function _is_admin($person)
    {
        static $admingroups = null;
        if ($admingroups == null)
        {
            $db = mysqli_connect($this->_config->host, $this->_config->dbuser, $this->_config->dbpass, $this->_config->database);
            if (mysqli_connect_errno())
            {
                throw new \RuntimeException(mysqli_connect_error());
            }
            $admingroups = array(0);
            $stmt = 'SELECT admingroup FROM sitegroup';
            $result = mysqli_query($db, 'SELECT admingroup FROM sitegroup');
            if (!$result)
            {
                throw new \RuntimeException(mysqli_error($db));
            }
            while ($row = mysqli_fetch_assoc($result))
            {
                $admingroups[] = (int) $row['admingroup'];
            }
        }

        $qb = new \midgard_query_builder('midgard_member');
        $qb->add_constraint('uid', '=', $person->id);
        $qb->add_constraint('gid', 'IN', $admingroups);
        return ($qb->count() > 0);
    }
}
?>
