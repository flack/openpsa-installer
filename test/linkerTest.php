<?php
/**
 * @package openpsa.installer
 * @author CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @copyright CONTENT CONTROL http://www.contentcontrol-berlin.de/
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */

use openpsa\installer\linker;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

/**
 * Simple linker tests
 *
 * @package openpsa.installer
 */
class linkerTest extends TestCase
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

    protected function setUp() : void
    {
        $this->basedir = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'test-basedir-' . uniqid();

        $this->input = $this->createMock('Symfony\Component\Console\Input\InputInterface');
        $this->output = $this->createMock('Symfony\Component\Console\Output\OutputInterface');
        $this->helperset = $this->createMock('Symfony\Component\Console\Helper\HelperSet');

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

    private function makepath(array $parts)
    {
        array_unshift($parts, $this->basedir);
        return implode(DIRECTORY_SEPARATOR, $parts);
    }

    protected function tearDown() : void
    {
        // read-only tests may leave directories that can't be cleaned up otherwise
        $this->fs->chmod($this->basedir, 0755, 0000, true);
        $this->fs->remove($this->basedir);
    }

    private function _get_linker(?OutputInterface $output = null, ?HelperSet $helperset = null) : linker
    {
        $linker = new linker($this->basedir, $this->input, $output ?? $this->output, $helperset ?? $this->helperset);
        $linker->set_schema_location($this->makepath(['var', 'schemas']) . DIRECTORY_SEPARATOR);
        return $linker;
    }

    private function answering(string ...$answers) : HelperSet
    {
        $helper = $this->createMock(QuestionHelper::class);
        $helper->expects($this->exactly(count($answers)))
            ->method('ask')
            ->willReturnOnConsecutiveCalls(...$answers);
        return new HelperSet(['question' => $helper]);
    }

    private function skip_if_root() : void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('File permissions are not enforced for root');
        }
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
        $this->assertFileDoesNotExist($this->makepath(['web', 'midcom-static', 'theme-name']));
    }

    #[Depends('testInstall')]
    public function testUninstall()
    {
        $linker = $this->_get_linker();
        $linker->install($this->basedir);

        $linker = $this->_get_linker();
        $linker->uninstall($this->basedir);

        $this->assertFileDoesNotExist($this->makepath(['web', 'midcom-static', 'component.name']));
        $this->assertFileDoesNotExist($this->makepath(['web', 'midcom-static', 'theme-name']));
        $this->assertFileDoesNotExist($this->makepath(['var', 'themes', 'theme-name']));
        $this->assertFileDoesNotExist($this->makepath(['schemas_location', 'component_name.xml']));
    }

    public function testGet_links()
    {
        $links = $this->_get_linker()->get_links($this->basedir);

        $this->assertCount(4, $links);
        $linknames = array_column($links, 'linkname');
        $this->assertContains($this->makepath(['web', 'midcom-static', 'component.name']), $linknames);
        $this->assertContains($this->makepath(['web', 'midcom-static', 'theme-name']), $linknames);
        $this->assertContains($this->makepath(['var', 'themes', 'theme-name']), $linknames);
        $this->assertContains($this->makepath(['var', 'schemas', 'component_name.xml']), $linknames);
        foreach ($links as $link) {
            $this->assertStringStartsWith('../../', $link['target']);
        }
    }

    public function testGet_links_ignores_hidden_and_non_xml_files()
    {
        $this->fs->mkdir($this->makepath(['static', '.hidden']));
        touch($this->makepath(['schemas', '.hidden.xml']));
        touch($this->makepath(['schemas', 'readme.txt']));

        $this->assertCount(4, $this->_get_linker()->get_links($this->basedir));
    }

    public function testUpdate()
    {
        $linker = $this->_get_linker();
        $linker->install($this->basedir);
        $old_links = $linker->get_links($this->basedir);

        $this->fs->remove($this->makepath(['themes']));
        $linker->update($this->basedir, $old_links);

        $this->assertTrue(is_link($this->makepath(['web', 'midcom-static', 'component.name'])));
        $this->assertFalse(is_link($this->makepath(['web', 'midcom-static', 'theme-name'])));
        $this->assertFalse(is_link($this->makepath(['var', 'themes', 'theme-name'])));
    }

    public function testLink_nonexistent_target()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot link to nonexistent path');
        $this->_get_linker()->link($this->makepath(['nonexistent']), $this->makepath(['link']));
    }

    public function testLink_keeps_existing_link_to_other_target()
    {
        $output = new BufferedOutput;
        $linker = $this->_get_linker($output);
        $other = $this->makepath(['other.xml']);
        $this->fs->dumpFile($other, 'other content');
        $linkname = $this->makepath(['var', 'schemas', 'component_name.xml']);
        symlink($other, $linkname);

        $linker->install($this->basedir);

        $this->assertSame($other, realpath($linkname));
        $this->assertStringContainsString('Skipping component_name.xml: Found Link in', $output->fetch());
    }

    public function testLink_keeps_existing_file()
    {
        $schema = $this->makepath(['var', 'schemas', 'component_name.xml']);
        $this->fs->dumpFile($schema, 'local content');

        $output = new BufferedOutput;
        $this->_get_linker($output)->install($this->basedir);

        $this->assertFalse(is_link($schema));
        $this->assertSame('local content', file_get_contents($schema));
        $this->assertStringContainsString('Found existing file', $output->fetch());
    }

    public function testLink_keeps_existing_directory()
    {
        $dirname = $this->makepath(['web', 'midcom-static', 'component.name']);
        $this->fs->mkdir($dirname);

        $output = new BufferedOutput;
        $this->_get_linker($output)->install($this->basedir);

        $this->assertFalse(is_link($dirname));
        $this->assertDirectoryExists($dirname);
        $this->assertStringContainsString('Found existing directory', $output->fetch());
    }

    public function testLink_overwrite_replaces_existing_file_directory_and_link()
    {
        $schema = $this->makepath(['var', 'schemas', 'component_name.xml']);
        $this->fs->dumpFile($schema, 'local content');
        $static = $this->makepath(['web', 'midcom-static', 'component.name']);
        $this->fs->dumpFile($static . '/local.css', 'local content');
        $other = $this->makepath(['other']);
        $this->fs->mkdir($other);
        $theme = $this->makepath(['var', 'themes', 'theme-name']);
        $this->fs->mkdir(dirname($theme));
        symlink($other, $theme);

        $output = new BufferedOutput;
        $linker = $this->_get_linker($output);
        $linker->set_overwrite(true);
        $linker->install($this->basedir);

        $this->assertTrue(is_link($schema));
        $this->assertSame($this->makepath(['schemas', 'component_name.xml']), realpath($schema));
        $this->assertTrue(is_link($static));
        $this->assertSame($this->paths['component_static'], realpath($static));
        $this->assertSame($this->makepath(['themes', 'theme-name']), realpath($theme));
        $this->assertDirectoryExists($other);
        $display = $output->fetch();
        $this->assertStringContainsString('Removing existing file', $display);
        $this->assertStringContainsString('Removing existing directory', $display);
        $this->assertStringContainsString('Removing link', $display);
    }

    public function testLink_replaces_dangling_link()
    {
        $this->fs->mkdir($this->makepath(['web', 'midcom-static']));
        $linkname = $this->makepath(['web', 'midcom-static', 'component.name']);
        symlink($this->makepath(['nonexistent']), $linkname);

        $output = new BufferedOutput;
        $this->_get_linker($output)->install($this->basedir);

        $this->assertSame($this->paths['component_static'], realpath($linkname));
        $this->assertStringContainsString('points to nonexistent path, removing', $output->fetch());
    }

    public function testRemove_dangling_links()
    {
        $linker = $this->_get_linker();
        $this->fs->mkdir([$this->makepath(['web', 'midcom-static']), $this->makepath(['var', 'themes'])]);
        $dangling = [
            $this->makepath(['web', 'midcom-static', 'gone']),
            $this->makepath(['var', 'themes', 'gone']),
            $this->makepath(['var', 'schemas', 'gone.xml']),
        ];
        foreach ($dangling as $link) {
            symlink($this->makepath(['nonexistent']), $link);
        }
        $linker->install($this->basedir);

        $linker->remove_dangling_links();

        foreach ($dangling as $link) {
            $this->assertFalse(is_link($link), $link);
        }
        $this->assertTrue(is_link($this->makepath(['web', 'midcom-static', 'component.name'])));
    }

    public function testLink_readonly_ignore()
    {
        $this->skip_if_root();
        $static_dir = $this->makepath(['web', 'midcom-static']);
        $this->fs->mkdir($static_dir);
        chmod($static_dir, 0555);

        $output = new BufferedOutput;
        // asked only once, although both component and theme static dirs are affected
        $this->_get_linker($output, $this->answering('i'))->install($this->basedir);

        $this->assertFalse(is_link($this->makepath(['web', 'midcom-static', 'component.name'])));
        $this->assertFalse(is_link($this->makepath(['web', 'midcom-static', 'theme-name'])));
        $this->assertTrue(is_link($this->makepath(['var', 'themes', 'theme-name'])));
        $this->assertStringContainsString('is not writeable', $output->fetch());
    }

    public function testLink_readonly_abort()
    {
        $this->skip_if_root();
        $static_dir = $this->makepath(['web', 'midcom-static']);
        $this->fs->mkdir($static_dir);
        chmod($static_dir, 0555);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Aborted by user command');
        $this->_get_linker(null, $this->answering('a'))->install($this->basedir);
    }

    public function testLink_readonly_invalid_answer()
    {
        $this->skip_if_root();
        $static_dir = $this->makepath(['web', 'midcom-static']);
        $this->fs->mkdir($static_dir);
        chmod($static_dir, 0555);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid input');
        $this->_get_linker(null, $this->answering('x'))->install($this->basedir);
    }
}
