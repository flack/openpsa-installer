<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use openpsa\installer\setup;
use openpsa\installer\midgard2\setup as setup_command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the midgard-portable setup helper
 *
 * @package openpsa.installer
 */
class setupTest extends TestCase
{
    private string $basedir;

    private string $home;

    private string|false $original_home;

    private Filesystem $fs;

    private BufferedOutput $output;

    protected function setUp() : void
    {
        $this->fs = new Filesystem;
        $this->basedir = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'setup-basedir-' . uniqid();
        $this->home = $this->basedir . DIRECTORY_SEPARATOR . 'home';
        $this->fs->mkdir($this->home);

        // midgard-portable writes its config files to $HOME/.midgard2/conf.d
        $this->original_home = getenv('HOME');
        putenv('HOME=' . $this->home);

        $this->output = new BufferedOutput;
    }

    protected function tearDown() : void
    {
        if ($this->original_home === false) {
            putenv('HOME');
        } else {
            putenv('HOME=' . $this->original_home);
        }
        $this->fs->chmod($this->basedir, 0755, 0000, true);
        $this->fs->remove($this->basedir);
    }

    private function create_setup(array $args = [], array $answers = []) : setup
    {
        $input = new ArrayInput($args, (new setup_command)->getDefinition());
        $helper = $this->createMock(QuestionHelper::class);
        $helper->expects($this->exactly(count($answers)))
            ->method('ask')
            ->willReturnOnConsecutiveCalls(...$answers);

        return new setup($input, $this->output, $this->basedir, new HelperSet(['question' => $helper]));
    }

    private function conf_dir() : string
    {
        return $this->home . '/.midgard2/conf.d/';
    }

    public function testPrepare_project_directory()
    {
        setup::prepare_project_directory($this->basedir);

        foreach (['config', 'var/cache', 'var/rcs', 'var/blobs', 'var/log', 'var/themes'] as $dir) {
            $this->assertDirectoryExists($this->basedir . '/' . $dir);
        }
    }

    public function testCreate_config_sqlite()
    {
        setup::prepare_project_directory($this->basedir);
        $config = $this->create_setup(['--dbtype' => 'SQLite'])->create_config();

        $project_name = basename($this->basedir);
        $this->assertSame('SQLite', $config->dbtype);
        $this->assertSame($project_name, $config->database);
        $this->assertSame($this->basedir . '/var', $config->dbdir);
        $this->assertSame($this->basedir . '/var/blobs', $config->blobdir);
        $this->assertSame($this->basedir . '/var/schemas', $config->sharedir);
        $this->assertSame($this->basedir . '/var/log/midgard.log', $config->logfilename);

        $this->assertFileExists($this->conf_dir() . $project_name);
        $this->assertTrue(is_link($this->basedir . '/config/midgard2.ini'));
        $this->assertSame(realpath($this->conf_dir() . $project_name), realpath($this->basedir . '/config/midgard2.ini'));
    }

    public function testCreate_config_mysql()
    {
        setup::prepare_project_directory($this->basedir);
        $config = $this->create_setup(['--dbtype' => 'MySQL'], ['dbuser', 'secret', 'dbname'])->create_config();

        $this->assertSame('MySQL', $config->dbtype);
        $this->assertSame('dbuser', $config->dbuser);
        $this->assertSame('secret', $config->dbpass);
        $this->assertSame('dbname', $config->database);
    }

    public function testCreate_config_asks_for_dbtype()
    {
        setup::prepare_project_directory($this->basedir);
        $config = $this->create_setup(['--dbtype' => ''], ['SQLite'])->create_config();

        $this->assertSame('SQLite', $config->dbtype);
    }

    public function testCreate_config_unsupported_dbtype()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported DB type Oracle');
        $this->create_setup(['--dbtype' => 'Oracle'])->create_config();
    }

    public function testCreate_config_link_failure_is_reported()
    {
        // without the config dir, the link to config/midgard2.ini can't be created
        // the linker asks what to do about the non-writeable directory: abort
        $config = $this->create_setup(['--dbtype' => 'SQLite'], ['a'])->create_config();

        $this->assertSame('SQLite', $config->dbtype);
        $this->assertStringContainsString('could not be linked', $this->output->fetch());
    }

    public function testPrepare_config_uses_project_config()
    {
        setup::prepare_project_directory($this->basedir);
        $this->create_setup(['--dbtype' => 'SQLite'])->create_config();
        $this->output->fetch();

        $config = $this->create_setup()->prepare_config();

        $this->assertSame('SQLite', $config->dbtype);
        $this->assertSame(basename($this->basedir), $config->database);
        $this->assertStringContainsString('Using config file found at ' . $this->basedir . '/config/midgard2.ini', $this->output->fetch());
    }

    public function testLoad_config_by_name()
    {
        setup::prepare_project_directory($this->basedir);
        $this->create_setup(['config' => 'named_project', '--dbtype' => 'SQLite'])->create_config();
        $this->output->fetch();

        $config = $this->create_setup(['config' => 'named_project'])->load_config();

        $this->assertSame('named_project', $config->database);
        $this->assertStringContainsString('Using config file found at ' . $this->conf_dir() . 'named_project', $this->output->fetch());
    }

    public function testLoad_config_unknown_name_creates_config()
    {
        setup::prepare_project_directory($this->basedir);
        $config = $this->create_setup(['config' => 'new_project', '--dbtype' => 'SQLite'])->load_config();

        $this->assertStringContainsString('Configuration file new_project not found.', $this->output->fetch());
        $this->assertSame('new_project', $config->database);
        $this->assertFileExists($this->conf_dir() . 'new_project');
    }

    public function testLoad_config_unreadable()
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('File permissions are not enforced for root');
        }
        setup::prepare_project_directory($this->basedir);
        $this->create_setup(['--dbtype' => 'SQLite'])->create_config();
        chmod($this->conf_dir() . basename($this->basedir), 0000);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not read config file');
        $this->create_setup()->load_config();
    }

    public function testPrepare_storage_without_config()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No config loaded');
        $this->create_setup()->prepare_storage();
    }
}
