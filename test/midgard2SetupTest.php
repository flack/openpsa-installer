<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use openpsa\installer\setup as helper;
use openpsa\installer\midgard2\setup;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end test for the midgard2:setup command
 *
 * midgard-portable keeps its connection in static state, so each test needs its own process
 *
 * @package openpsa.installer
 */
#[RunTestsInSeparateProcesses]
class midgard2SetupTest extends TestCase
{
    private string $basedir;

    private Filesystem $fs;

    protected function setUp() : void
    {
        $this->fs = new Filesystem;
        $this->basedir = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'midgard2-basedir-' . uniqid();
        $this->fs->mkdir($this->basedir . '/home');
        $this->fs->mkdir($this->basedir . '/project');
        putenv('HOME=' . $this->basedir . '/home');
    }

    protected function tearDown() : void
    {
        $this->fs->remove($this->basedir);
    }

    public function testExecute_sqlite()
    {
        $project = $this->basedir . '/project';
        helper::prepare_project_directory($project);

        $app = new Application;
        $command = new setup;
        $app->addCommand($command);
        $command->set_basepath($project);

        $tester = new CommandTester($command);
        $status = $tester->execute(['--dbtype' => 'SQLite']);

        $this->assertSame(0, $status, $tester->getDisplay());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Configuration file ' . $this->basedir . '/home/.midgard2/conf.d/project created', $display);
        $this->assertStringContainsString('Preparing SQLite storage', $display);
        $this->assertFileExists($project . '/config/midgard2.ini');
        $this->assertDirectoryExists($project . '/var/blobs/F/F');
    }

    public function testInstall_sqlite()
    {
        $project = $this->basedir . '/project';

        $this->assertSame(0, setup::install($project, 'SQLite'));
        $this->assertFileExists($project . '/config/midgard2.ini');
        $this->assertDirectoryExists($project . '/var/blobs/F/F');
    }
}
