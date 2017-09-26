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
use Symfony\Component\Filesystem\Filesystem;

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

        $this->vendorDir = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'composer-test-vendor';
        $this->fs->mkdir($this->vendorDir);

        $this->composer = new Composer();
        $this->config = new Config();
        $this->composer->setConfig($this->config);
        $this->composer->setPackage($this->createPackageMock());

        $this->config->merge([
            'config' => [
                'vendor-dir' => $this->vendorDir,
            ]
        ]);

        $this->dm = $this->getMockBuilder('Composer\Downloader\DownloadManager')
                ->disableOriginalConstructor()
                ->getMock();
        $this->composer->setDownloadManager($this->dm);

        $this->repository = $this->mock('Composer\Repository\InstalledRepositoryInterface');
        $this->io = $this->mock('Composer\IO\IOInterface');

        $this->installer = new installer($this->io, $this->composer);
    }

    private function mock($classname)
    {
        if (method_exists($this, 'getMock')) {
            return $this->getMock($classname);
        }
        return $this->createMock($classname);
    }


    protected function tearDown()
    {
        $this->fs->remove($this->vendorDir);
    }

    protected function createPackageMock(array $extra = [])
    {
        $package = $this->getMockBuilder('Composer\Package\RootPackage')
                ->setConstructorArgs([md5(rand()), '1.0.0.0', '1.0.0'])
                ->getMock();

        return $package;
    }

    public function testSupports()
    {
        $this->assertTrue($this->installer->supports('midcom-package'));
    }
}
