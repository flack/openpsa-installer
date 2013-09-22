<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use openpsa\installer\linker;
use Composer\Util\Filesystem;
use Composer\IO\IOInterface;

/**
 * Simple linker tests
 *
 * @package openpsa.installer
 */
class linkerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    protected $basedir;

    /**
     * @var Composer\Util\Filesystem
     */
    protected $fs;

    /**
     * @var Composer\IO\IOInterface
     */
    protected $io;

    protected function setUp()
    {
        $this->basedir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'test-basedir-' . uniqid();
        $this->io = $this->getMock('Composer\IO\IOInterface');

        $this->fs = new Filesystem;
        $this->fs->ensureDirectoryExists($this->basedir);
        $this->fs->ensureDirectoryExists($this->basedir . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'component.name');
        $this->fs->ensureDirectoryExists($this->basedir . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'theme-name' . DIRECTORY_SEPARATOR . 'static');

        $this->fs->ensureDirectoryExists($this->basedir . DIRECTORY_SEPARATOR . 'schemas_location');
        $this->fs->ensureDirectoryExists($this->basedir . DIRECTORY_SEPARATOR . 'schemas');
        touch($this->basedir . DIRECTORY_SEPARATOR . 'schemas' . DIRECTORY_SEPARATOR . 'component_name.xml');
    }

    protected function tearDown()
    {
        $this->fs->removeDirectory($this->basedir);
    }

    private function _get_linker()
    {
        $linker = new linker($this->basedir, $this->io);
        $linker->set_schema_location($this->basedir . DIRECTORY_SEPARATOR . 'schemas_location' . DIRECTORY_SEPARATOR);
        return $linker;
    }

    public function testInstall()
    {
        $linker = $this->_get_linker();
        $linker->install($this->basedir);

        $this->assertFileExists($this->basedir . DIRECTORY_SEPARATOR . 'web');
        $this->assertFileExists($this->basedir . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'midcom-static');
        $this->assertFileExists($this->basedir . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'midcom-static' . DIRECTORY_SEPARATOR . 'component.name');
        $this->assertFileExists($this->basedir . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'midcom-static' . DIRECTORY_SEPARATOR . 'theme-name');
        $this->assertFileExists($this->basedir . DIRECTORY_SEPARATOR . 'schemas_location' . DIRECTORY_SEPARATOR . 'component_name.xml');

        $linker = $this->_get_linker();
        $linker->install($this->basedir);
    }

    public function testInstall_incomplete_theme_dir()
    {
        $this->fs->removeDirectory($this->basedir . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'theme-name' . DIRECTORY_SEPARATOR . 'static');

        $linker = $this->_get_linker();
        $linker->install($this->basedir);

        $this->assertFileExists($this->basedir . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'midcom-static' . DIRECTORY_SEPARATOR . 'component.name');
        $this->assertFileNotExists($this->basedir . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'midcom-static' . DIRECTORY_SEPARATOR . 'theme-name');
    }

    /**
     * @depends testInstall
     */
    public function testUninstall()
    {
        $linker = $this->_get_linker();
        $linker->install($this->basedir);

        $linker = $this->_get_linker();
        $linker->uninstall($this->basedir);

        $this->assertFileNotExists($this->basedir . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'midcom-static' . DIRECTORY_SEPARATOR . 'component.name');
        $this->assertFileNotExists($this->basedir . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'midcom-static' . DIRECTORY_SEPARATOR . 'theme-name');
        $this->assertFileNotExists($this->basedir . DIRECTORY_SEPARATOR . 'schemas_location' . DIRECTORY_SEPARATOR . 'component_name.xml');
    }
}
