<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use openpsa\installer\linker;
use Symfony\Component\Filesystem\Filesystem;

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
     * @var Filesystem
     */
    protected $fs;

    /**
     * @var Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * @var Symfony\Component\Console\Helper\HelperSet
     */
    protected $helperset;

    private $paths = [];

    protected function setUp()
    {
        $this->basedir = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'test-basedir-' . uniqid();

        $this->input = $this->mock('Symfony\Component\Console\Input\InputInterface');
        $this->output = $this->mock('Symfony\Component\Console\Output\OutputInterface');
        $this->helperset = $this->mock('Symfony\Component\Console\Helper\HelperSet');

        $this->fs = new Filesystem;
        $this->fs->mkdir($this->basedir);

        $this->paths = [
            'component_static' => $this->makepath(['static', 'component.name']),
            'theme_static' => $this->makepath(['themes', 'theme-name', 'static']),
            'vendor_static' => $this->makepath(['vendor', 'openpsa', 'test', 'static', 'vendor.component'])
        ];

        $this->fs->mkdir($this->paths['component_static']);
        $this->fs->mkdir($this->paths['theme_static']);
        $this->fs->mkdir($this->paths['vendor_static']);

        $this->fs->mkdir($this->makepath(['schemas_location']));
        $this->fs->mkdir($this->makepath(['schemas']));
        touch($this->makepath(['schemas', 'component_name.xml']));
    }

    private function mock($classname)
    {
        if (method_exists($this, 'getMock')) {
            return $this->getMock($classname);
        }
        return $this->createMock($classname);
    }

    private function makepath(array $parts)
    {
        array_unshift($parts, $this->basedir);
        return implode(DIRECTORY_SEPARATOR, $parts);
    }

    protected function tearDown()
    {
        $this->fs->remove($this->basedir);
    }

    private function _get_linker()
    {
        $linker = new linker($this->basedir, $this->input, $this->output, $this->helperset);
        $linker->set_schema_location($this->makepath(['var', 'schemas']) . DIRECTORY_SEPARATOR);
        return $linker;
    }

    public function testInstall()
    {
        $linker = $this->_get_linker();
        $linker->install($this->basedir);

        $component_static_link = $this->makepath(['web', 'midcom-static', 'component.name']);
        $this->assertFileExists($component_static_link);
        $this->assertSame(realpath($component_static_link), $this->paths['component_static']);
        $this->assertFileExists($this->makepath(['web', 'midcom-static', 'theme-name']));
        $this->assertFileExists($this->makepath(['var', 'schemas', 'component_name.xml']));
        $themes_link = $this->makepath(['var', 'themes', 'theme-name']);
        $this->assertFileExists($themes_link);
        $this->assertSame(dirname($this->paths['theme_static']), realpath($themes_link));

        $linker = $this->_get_linker();
        $linker->install($this->basedir);
    }

    public function testInstall_vendor_static()
    {
        $linker = $this->_get_linker();
        $linker->install($this->makepath(['vendor', 'openpsa', 'test']));

        $vendor_static_link = $this->makepath(['web', 'midcom-static', 'vendor.component']);
        $this->assertFileExists($vendor_static_link);
        $this->assertSame(realpath($vendor_static_link), $this->paths['vendor_static']);
    }

    public function testInstall_incomplete_theme_dir()
    {
        $this->fs->remove($this->makepath(['themes', 'theme-name', 'static']));

        $linker = $this->_get_linker();
        $linker->install($this->basedir);

        $this->assertFileExists($this->makepath(['web', 'midcom-static', 'component.name']));
        $this->assertFileNotExists($this->makepath(['web', 'midcom-static', 'theme-name']));
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

        $this->assertFileNotExists($this->makepath(['web', 'midcom-static', 'component.name']));
        $this->assertFileNotExists($this->makepath(['web', 'midcom-static', 'theme-name']));
        $this->assertFileNotExists($this->makepath(['var', 'themes', 'theme-name']));
        $this->assertFileNotExists($this->makepath(['schemas_location', 'component_name.xml']));
    }
}
