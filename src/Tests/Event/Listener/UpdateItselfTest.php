<?php

/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\AnimeDbBundle\Tests\Event\Listener;

use AnimeDb\Bundle\AnimeDbBundle\Event\Listener\UpdateItself;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Test UpdateItself
 *
 * @package AnimeDb\Bundle\AnimeDbBundle\Tests\Event\Listener
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class UpdateItselfTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Listener
     *
     * @var \AnimeDb\Bundle\AnimeDbBundle\Event\Listener\UpdateItself
     */
    protected $listener;

    /**
     * Root dir
     *
     * @var string
     */
    protected $root_dir;

    /**
     * Event dir
     *
     * @var string
     */
    protected $event_dir;

    /**
     * Link to monitor archive
     *
     * @var string
     */
    protected $monitor;

    /**
     * ZipArchive
     *
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $zip;

    /**
     * Event
     *
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $event;

    /**
     * Filesystem
     *
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected $fs;

    /**
     * Construct
     *
     * @param string $name
     * @param array $data
     * @param string $dataName
     */
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->fs = new Filesystem();
    }

    /**
     * (non-PHPdoc)
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
        // real path /foo/
        $this->root_dir = sys_get_temp_dir().'/tests/foo/bar/';
        $this->event_dir = sys_get_temp_dir().'/tests/baz/';
        $this->fs->mkdir([$this->root_dir, $this->event_dir]);
        $this->monitor = tempnam(sys_get_temp_dir().'/tests/', 'monitor');
        $this->zip = $this->getMock('\ZipArchive');

        $this->listener = new UpdateItself($this->zip, $this->root_dir, $this->monitor);

        $this->event = $this->getMockBuilder('\AnimeDb\Bundle\AnimeDbBundle\Event\UpdateItself\Downloaded')
            ->disableOriginalConstructor()
            ->getMock();
        $this->event
            ->expects($this->any())
            ->method('getPath')
            ->willReturn($this->event_dir);
    }

    /**
     * (non-PHPdoc)
     * @see PHPUnit_Framework_TestCase::tearDown()
     */
    protected function tearDown()
    {
        parent::tearDown();
        $this->fs->remove(sys_get_temp_dir().'/tests/');
        @unlink(sys_get_temp_dir().'/'.basename($this->monitor));
    }

    /**
     * Test merge composer requirements
     */
    public function testOnAppDownloadedMergeComposerRequirements()
    {
        $composer = json_encode([
            'name' => 'foo',
            'require' => [
                'bar',
                'baz'
            ],
        ]);
        file_put_contents($this->root_dir.'/../composer.json', $composer);
        file_put_contents($this->event_dir.'/composer.json', $composer);

        $this->listener->onAppDownloadedMergeComposerRequirements($this->event); // test

        $this->assertFileEquals($this->root_dir.'/../composer.json', $this->event_dir.'/composer.json');
    }

    /**
     * Test do merge composer requirements
     */
    public function testOnAppDownloadedMergeComposerRequirementsMerge()
    {
        $old_composer = [
            'name' => 'foo',
            'require' => [
                'bar'
            ],
        ];
        $new_composer = [
            'name' => 'foo',
            'require' => [
                'baz'
            ],
        ];
        file_put_contents($this->root_dir.'/../composer.json', json_encode($old_composer));
        file_put_contents($this->event_dir.'/composer.json', json_encode($new_composer));

        $this->listener->onAppDownloadedMergeComposerRequirements($this->event); // test

        $expected = $old_composer;
        $expected['require'] = array_merge($expected['require'], $new_composer['require']);
        $this->assertEquals(
            json_encode($expected, JSON_PRETTY_PRINT),
            file_get_contents($this->event_dir.'/composer.json')
        );
    }

    /**
     * Test merge configs
     */
    public function testOnAppDownloadedMergeConfigs()
    {
        $files = [
            'app/config/parameters.yml',
            'app/config/vendor_config.yml',
            'app/config/routing.yml',
            'app/bundles.php'
        ];
        $this->fs->mkdir([$this->event_dir.'/app/config/', $this->event_dir.'/app/']);
        $this->initFiles($files, $this->root_dir.'../');

        $this->listener->onAppDownloadedMergeConfigs($this->event); // test

        foreach ($files as $file) {
            $this->assertFileEquals($this->root_dir.'../'.$file, $this->event_dir.$file);
        }
    }

    /**
     * Test change sccess to shell files
     */
    public function testOnAppDownloadedChangeAccessToFiles()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Will not run on OS Windows');
        } else {
            $file1 = $this->event->getPath().'AnimeDB';
            $file2 = $this->event->getPath().'app/console';
            $this->fs->mkdir($this->event->getPath().'app');
            touch($file1);
            touch($file2);
            chmod($file1, 0666);
            chmod($file2, 0666);

            $this->listener->onAppDownloadedChangeAccessToFiles($this->event); // test

            $this->assertEquals(0755, fileperms($file1) & 0777);
            $this->assertEquals(0755, fileperms($file2) & 0777);
        }
    }

    /**
     * Test remove old files on merge bin run
     */
    public function testOnAppDownloadedMergeBinRunRemoveOld()
    {
        $files = [
            'bin/AnimeDB_Run.vbs',
            'bin/AnimeDB_Stop.vbs',
            'AnimeDB_Run.vbs',
            'AnimeDB_Stop.vbs'
        ];
        $this->initFiles($files, $this->root_dir.'../');

        // no test merge config.ini in this test
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            touch($this->root_dir.'../config.ini');
            touch($this->event_dir.'config.ini');
        }

        $this->listener->onAppDownloadedMergeBinRun($this->event); // test

        foreach ($files as $file) {
            $this->assertFileNotExists($this->root_dir.'../'.$file);
        }
    }

    /**
     * Test install monitor
     */
    public function testOnAppDownloadedMergeBinRunInstallMonitor()
    {
        if (!defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('This test is only for OS Windows');
        }
        $this->zip
            ->expects($this->once())
            ->method('open')
            ->with(sys_get_temp_dir().'/'.basename($this->monitor))
            ->willReturn(true);
        $this->zip
            ->expects($this->once())
            ->method('extractTo')
            ->with($this->event_dir);
        $this->zip
            ->expects($this->once())
            ->method('close');

        $this->listener->onAppDownloadedMergeBinRun($this->event); // test
    }

    /**
     * Test failed unzip monitor
     *
     * @expectedException \RuntimeException
     */
    public function testOnAppDownloadedMergeBinRunFailedInstallMonitor()
    {
        if (!defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('This test is only for OS Windows');
        }
        $this->zip
            ->expects($this->once())
            ->method('open')
            ->willReturn(false);

        $this->listener->onAppDownloadedMergeBinRun($this->event); // test
    }

    /**
     * Test merge config.ini
     */
    public function testOnAppDownloadedMergeBinRun()
    {
        if (!defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('This test is only for OS Windows');
        }
        file_put_contents($this->root_dir.'../config.ini', '[General]
addr=localhost
port=80
php=php
');
        file_put_contents($this->event_dir.'config.ini', '[General]
addr='.UpdateItself::DEFAULT_ADDRESS.'
port='.UpdateItself::DEFAULT_PORT.'
php='.UpdateItself::DEFAULT_PHP.'
');
        $this->listener->onAppDownloadedMergeBinRun($this->event); // test

        $this->assertFileEquals($this->root_dir.'../config.ini', $this->event_dir.'config.ini');
    }

    /**
     * Test merge bin service
     *
     * @dataProvider getService
     */
    public function testOnAppDownloadedMergeBinService($root_file, $root_code, $event_code)
    {
        $this->fs->mkdir($this->root_dir.'../bin/');
        file_put_contents($root_file, $root_code);
        file_put_contents($this->event_dir.'AnimeDB', $event_code);

        $this->listener->onAppDownloadedMergeBinService($this->event); // test

        $this->assertFileEquals($root_file, $this->event_dir.'AnimeDB');
    }

    /**
     * Data provider service
     *
     * @return array
     */
    public function getService()
    {
        $this->setUp();

        $default_code = "#!/bin/sh
addr='".UpdateItself::DEFAULT_ADDRESS."'
port=".UpdateItself::DEFAULT_PORT."
path=".UpdateItself::DEFAULT_PATH."
";
        $changed_code = "#!/bin/sh
addr='localhost'
port=80
path=.
";
        return [
            [$this->root_dir.'../bin/service', $changed_code, $default_code],
            [$this->root_dir.'../bin/service', $default_code, $default_code],
            [$this->root_dir.'../AnimeDB', $changed_code, $default_code],
            [$this->root_dir.'../AnimeDB', $default_code, $default_code]
        ];
    }

    /**
     * Init files
     *
     * @param string[] $files
     * @param string $dir
     */
    protected function initFiles(array $files, $dir)
    {
        foreach ($files as $file) {
            if (!is_dir($path = dirname($dir.$file))) {
                $this->fs->mkdir($path);
            }
            file_put_contents($dir.$file, $file);
        }
    }
}