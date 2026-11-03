<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use openpsa\installer\links\rebuild;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the links:rebuild command
 *
 * @package openpsa.installer
 */
class linksRebuildTest extends TestCase
{
    private string $basedir;

    private Filesystem $fs;

    protected function setUp() : void
    {
        $this->fs = new Filesystem;
        $this->basedir = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'test-basedir-' . uniqid();

        // root package
        $this->fs->mkdir($this->basedir . '/static/root.component');
        // midcom-package
        $this->fs->mkdir($this->basedir . '/vendor/openpsa/pkg/static/pkg.component');
        $this->fs->mkdir($this->basedir . '/vendor/openpsa/pkg/schemas');
        touch($this->basedir . '/vendor/openpsa/pkg/schemas/pkg.xml');
        // unrelated library, must be ignored
        $this->fs->mkdir($this->basedir . '/vendor/other/lib/static/lib.component');

        $this->write_installed_json([
            ['name' => 'openpsa/pkg', 'type' => 'midcom-package', 'install-path' => '../openpsa/pkg'],
            ['name' => 'other/lib', 'type' => 'library', 'install-path' => '../other/lib'],
            ['name' => 'gone/pkg', 'type' => 'midcom-package', 'install-path' => '../gone/pkg'],
        ]);
    }

    protected function tearDown() : void
    {
        $this->fs->remove($this->basedir);
    }

    private function write_installed_json(array $packages, string $vendor_dir = 'vendor') : void
    {
        $this->fs->dumpFile($this->basedir . '/' . $vendor_dir . '/composer/installed.json', json_encode(['packages' => $packages]));
    }

    private function run_command() : CommandTester
    {
        $app = new Application;
        $command = new rebuild;
        $app->addCommand($command);
        $command->set_basepath($this->basedir);

        $tester = new CommandTester($command);
        $tester->execute([]);
        return $tester;
    }

    public function testExecute()
    {
        $static = $this->basedir . '/web/midcom-static';
        $this->fs->mkdir($static);
        // stale link with wrong target, should be replaced
        symlink('../../vendor/other/lib/static/lib.component', $static . '/pkg.component');
        // dangling link, should be removed
        symlink('../../nonexistent', $static . '/old.component');
        // regular file, must not be touched
        touch($static . '/custom.txt');

        $tester = $this->run_command();

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame($this->basedir . '/static/root.component', realpath($static . '/root.component'));
        $this->assertSame($this->basedir . '/vendor/openpsa/pkg/static/pkg.component', realpath($static . '/pkg.component'));
        $this->assertSame('../../vendor/openpsa/pkg/static/pkg.component', readlink($static . '/pkg.component'));
        $this->assertSame($this->basedir . '/vendor/openpsa/pkg/schemas/pkg.xml', realpath($this->basedir . '/var/schemas/pkg.xml'));
        $this->assertFalse(is_link($static . '/old.component'));
        $this->assertFileDoesNotExist($static . '/lib.component');
        $this->assertFileExists($static . '/custom.txt');
        $this->assertStringContainsString('Rebuilt links for 2 package(s)', $tester->getDisplay());
    }

    public function testExecute_custom_vendor_dir()
    {
        $this->fs->rename($this->basedir . '/vendor', $this->basedir . '/lib');
        file_put_contents($this->basedir . '/composer.json', json_encode(['config' => ['vendor-dir' => 'lib']]));

        $tester = $this->run_command();

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame($this->basedir . '/lib/openpsa/pkg/static/pkg.component', realpath($this->basedir . '/web/midcom-static/pkg.component'));
    }

    public function testExecute_missing_installed_json()
    {
        $this->fs->remove($this->basedir . '/vendor/composer');

        $this->expectException(RuntimeException::class);
        $this->run_command();
    }
}
