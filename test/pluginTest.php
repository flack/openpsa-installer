<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use openpsa\installer\installer;
use openpsa\installer\plugin;
use Composer\Composer;
use Composer\Config;
use Composer\Downloader\DownloadManager;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use PHPUnit\Framework\TestCase;

/**
 * Plugin tests
 *
 * @package openpsa.installer
 */
class pluginTest extends TestCase
{
    private function create_composer(InstallationManager $im) : Composer
    {
        $composer = new Composer();
        $config = new Config();
        $config->merge(['config' => ['vendor-dir' => __DIR__ . '/plugin-test-vendor']]);
        $composer->setConfig($config);
        $composer->setDownloadManager($this->createMock(DownloadManager::class));
        $composer->setInstallationManager($im);
        return $composer;
    }

    public function testActivate()
    {
        $im = $this->createMock(InstallationManager::class);
        $im->expects($this->once())
            ->method('addInstaller')
            ->with($this->isInstanceOf(installer::class));

        (new plugin)->activate($this->create_composer($im), $this->createMock(IOInterface::class));
    }

    public function testDeactivate()
    {
        $im = $this->createMock(InstallationManager::class);
        $im->expects($this->once())
            ->method('removeInstaller')
            ->with($this->isInstanceOf(installer::class));

        (new plugin)->deactivate($this->create_composer($im), $this->createMock(IOInterface::class));
    }

    public function testUninstall()
    {
        $im = $this->createMock(InstallationManager::class);
        $im->expects($this->never())->method($this->anything());

        (new plugin)->uninstall($this->create_composer($im), $this->createMock(IOInterface::class));
    }
}
