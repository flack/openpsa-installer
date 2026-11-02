<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use openpsa\installer\installer;
use Composer\Composer;
use Composer\Config;
use Composer\Downloader\DownloadManager;
use Composer\IO\ConsoleIO;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Script\Event;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

use function React\Promise\resolve;

/**
 * Installer tests
 *
 * @package openpsa.installer
 */
class installerTest extends TestCase
{
    private installer $installer;

    private Composer $composer;

    private string $basedir;

    private string $vendorDir;

    private DownloadManager $dm;

    private InstalledRepositoryInterface $repository;

    private ConsoleIO $io;

    private BufferedOutput $output;

    private Filesystem $fs;

    protected function setUp() : void
    {
        $this->fs = new Filesystem;

        $this->basedir = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'installer-basedir-' . uniqid();
        $this->vendorDir = $this->basedir . DIRECTORY_SEPARATOR . 'vendor';
        $this->fs->mkdir($this->vendorDir);

        $this->composer = new Composer();
        $config = new Config();
        $config->merge([
            'config' => [
                'vendor-dir' => $this->vendorDir,
            ]
        ]);
        $this->composer->setConfig($config);
        $this->composer->setPackage(new RootPackage('openpsa/root', '1.0.0.0', '1.0.0'));

        $this->dm = $this->createMock(DownloadManager::class);
        // the real download manager returns promises; PHPUnit's default stub would never resolve
        $this->dm->method('install')->willReturnCallback(fn() => resolve(null));
        $this->dm->method('remove')->willReturnCallback(fn() => resolve(null));
        $this->composer->setDownloadManager($this->dm);

        $this->repository = $this->createMock(InstalledRepositoryInterface::class);

        $this->output = new BufferedOutput;
        $this->io = new ConsoleIO(new ArrayInput([]), $this->output, new HelperSet([new QuestionHelper]));

        $this->installer = new installer($this->io, $this->composer);
    }

    protected function tearDown() : void
    {
        $this->fs->remove($this->basedir);
    }

    private function create_package() : Package
    {
        $package = new Package('openpsa/test', '1.0.0.0', '1.0.0');
        $package->setType('midcom-package');
        $this->fs->mkdir($this->vendorDir . '/openpsa/test/static/test.component');
        $this->fs->mkdir($this->vendorDir . '/openpsa/test/static/old.component');
        return $package;
    }

    private function static_link(string $name) : string
    {
        return $this->basedir . '/web/midcom-static/' . $name;
    }

    public function testSupports()
    {
        $this->assertTrue($this->installer->supports('midcom-package'));
        $this->assertFalse($this->installer->supports('library'));
    }

    public function testInstall()
    {
        $package = $this->create_package();

        $promise = $this->installer->install($this->repository, $package);

        $this->assertNotNull($promise);
        $this->assertTrue(is_link($this->static_link('test.component')));
        $this->assertSame(realpath($this->vendorDir . '/openpsa/test/static/test.component'), realpath($this->static_link('test.component')));
    }

    public function testUpdate()
    {
        $package = $this->create_package();
        $this->installer->install($this->repository, $package);
        $this->assertTrue(is_link($this->static_link('old.component')));

        $target = new Package('openpsa/test', '1.1.0.0', '1.1.0');
        $target->setType('midcom-package');

        // simulate the new version no longer shipping old.component
        $this->dm->method('update')->willReturnCallback(function () {
            $this->fs->remove($this->vendorDir . '/openpsa/test/static/old.component');
            return resolve(null);
        });
        $this->repository->method('hasPackage')->willReturn(true);

        $promise = $this->installer->update($this->repository, $package, $target);

        $this->assertNotNull($promise);
        $this->assertTrue(is_link($this->static_link('test.component')));
        $this->assertFalse(is_link($this->static_link('old.component')));
    }

    public function testUninstall()
    {
        $package = $this->create_package();
        $this->installer->install($this->repository, $package);
        $this->repository->method('hasPackage')->willReturn(true);

        $promise = $this->installer->uninstall($this->repository, $package);

        $this->assertNotNull($promise);
        $this->assertFalse(is_link($this->static_link('test.component')));
        $this->assertFalse(is_link($this->static_link('old.component')));
    }

    public function testSetup_root_package()
    {
        $this->fs->mkdir($this->basedir . '/static/root.component');
        $this->fs->mkdir($this->basedir . '/web/midcom-static');
        symlink($this->basedir . '/nonexistent', $this->static_link('dangling'));

        $cwd = getcwd();
        chdir($this->basedir);
        try {
            installer::setup_root_package(new Event('post-update-cmd', $this->composer, $this->io));
        } finally {
            chdir($cwd);
        }

        foreach (['config', 'var/cache', 'var/rcs', 'var/blobs', 'var/log', 'var/themes'] as $dir) {
            $this->assertDirectoryExists($this->basedir . '/' . $dir);
        }
        $this->assertTrue(is_link($this->static_link('root.component')));
        $this->assertFalse(is_link($this->static_link('dangling')));
    }

    public function testSetup_root_package_install_keeps_dangling_links()
    {
        $this->fs->mkdir($this->basedir . '/web/midcom-static');
        symlink($this->basedir . '/nonexistent', $this->static_link('dangling'));

        $cwd = getcwd();
        chdir($this->basedir);
        try {
            installer::setup_root_package(new Event('post-install-cmd', $this->composer, $this->io));
        } finally {
            chdir($cwd);
        }

        $this->assertTrue(is_link($this->static_link('dangling')));
    }
}
