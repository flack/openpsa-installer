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

    private $paths = array();

    protected function setUp()
    {
        $this->basedir = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'test-basedir-' . uniqid();
        $this->io = $this->getMock('Composer\IO\IOInterface');

        $this->fs = new Filesystem;
        $this->fs->ensureDirectoryExists($this->basedir);

        $this->paths = array
        (
            'component_static' => $this->makepath(array('static', 'component.name')),
            'theme_static' => $this->makepath(array('themes', 'theme-name', 'static')),
            'vendor_static' => $this->makepath(array('vendor', 'openpsa' , 'test' , 'static', 'vendor.component'))
        );

        $this->fs->ensureDirectoryExists($this->paths['component_static']);
        $this->fs->ensureDirectoryExists($this->paths['theme_static']);
        $this->fs->ensureDirectoryExists($this->paths['vendor_static']);

        $this->fs->ensureDirectoryExists($this->makepath(array('schemas_location')));
        $this->fs->ensureDirectoryExists($this->makepath(array('schemas')));
        touch($this->makepath(array('schemas', 'component_name.xml')));
    }

    private function makepath(array $parts)
    {
        array_unshift($parts, $this->basedir);
        return implode(DIRECTORY_SEPARATOR, $parts);
    }

    protected function tearDown()
    {
        $this->fs->removeDirectory($this->basedir);
    }

    private function _get_linker()
    {
        $linker = new linker($this->basedir, $this->io);
        $linker->set_schema_location($this->makepath(array('schemas_location')) . DIRECTORY_SEPARATOR);
        return $linker;
    }

    public function testInstall()
    {
        $linker = $this->_get_linker();
        $linker->install($this->basedir);

        $component_static_link = $this->makepath(array('web', 'midcom-static', 'component.name'));
        $this->assertFileExists($component_static_link);
        $this->assertSame(realpath($component_static_link), $this->paths['component_static']);
        $this->assertFileExists($this->makepath(array('web', 'midcom-static', 'theme-name')));
        $this->assertFileExists($this->makepath(array('schemas_location', 'component_name.xml')));

        $linker = $this->_get_linker();
        $linker->install($this->basedir);
    }

    public function testInstall_vendor_static()
    {
        $linker = $this->_get_linker();
        $linker->install($this->makepath(array('vendor', 'openpsa' , 'test')));

        $vendor_static_link = $this->makepath(array('web', 'midcom-static', 'vendor.component'));
        $this->assertFileExists($vendor_static_link);
        $this->assertSame(realpath($vendor_static_link), $this->paths['vendor_static']);
    }

    public function testInstall_incomplete_theme_dir()
    {
        $this->fs->removeDirectory($this->makepath(array('themes', 'theme-name', 'static')));

        $linker = $this->_get_linker();
        $linker->install($this->basedir);

        $this->assertFileExists($this->makepath(array('web', 'midcom-static', 'component.name')));
        $this->assertFileNotExists($this->makepath(array('web', 'midcom-static', 'theme-name')));
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

        $this->assertFileNotExists($this->makepath(array('web', 'midcom-static', 'component.name')));
        $this->assertFileNotExists($this->makepath(array('web', 'midcom-static', 'theme-name')));
        $this->assertFileNotExists($this->makepath(array('schemas_location', 'component_name.xml')));
    }
}
