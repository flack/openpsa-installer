<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use openpsa\installer\installer;
use Composer\Installer\LibraryInstaller;
use Composer\Util\Filesystem;
use Composer\Test\TestCase;
use Composer\Composer;
use Composer\Config;

/**
 * Simple installer tests
 *
 * @package openpsa.installer
 */
class installerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var installer
     */
    protected $installer;

    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var string
     */
    protected $vendorDir;

    /**
     * @var Composer\Downloader\DownloadManager
     */
    protected $dm;

    /**
     * @var Composer\Repository\InstalledRepositoryInterface
     */
    protected $repository;

    /**
     * @var Composer\IO\IOInterface
     */
    protected $io;

    /**
     * @var Filesystem
     */
    protected $fs;

    protected function setUp()
    {
        $this->fs = new Filesystem;

        $this->vendorDir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'composer-test-vendor';
        $this->fs->ensureDirectoryExists($this->vendorDir);

        $this->composer = new Composer();
        $this->config = new Config();
        $this->composer->setConfig($this->config);
        $this->composer->setPackage($this->createPackageMock());

        $this->config->merge(array
        (
            'config' => array
            (
                'vendor-dir' => $this->vendorDir,
            )
        ));

        $this->dm = $this->getMockBuilder('Composer\Downloader\DownloadManager')
                ->disableOriginalConstructor()
                ->getMock();
        $this->composer->setDownloadManager($this->dm);

        $this->repository = $this->getMock('Composer\Repository\InstalledRepositoryInterface');
        $this->io = $this->getMock('Composer\IO\IOInterface');

        $this->installer = new installer($this->io, $this->composer);
    }

    protected function tearDown()
    {
        $this->fs->removeDirectory($this->vendorDir);
    }

    protected function createPackageMock(array $extra = array())
    {
        $package = $this->getMockBuilder('Composer\Package\RootPackage')
                ->setConstructorArgs(array(md5(rand()), '1.0.0.0', '1.0.0'))
                ->getMock();

        return $package;
    }

    public function testSupports()
    {
        $this->assertTrue($this->installer->supports('midcom-package'));
    }
}
