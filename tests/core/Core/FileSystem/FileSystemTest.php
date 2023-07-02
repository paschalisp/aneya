<?php
require_once (__DIR__ . '/../../../../aneya.php');

use aneya\Core\CMS;
use aneya\FileSystem\FileSystem;
use PHPUnit\Framework\TestCase;

class FileSystemTest extends TestCase {
	public function testStart() {
		$this->assertTrue(true);

		return CMS::filesystem();
	}

	/**
	 * @depends testStart
	 * @param FileSystem $fs
	 */
	public function testFileMethods(FileSystem $fs) {
		$fs->overwritePolicy = FileSystem::OverwritePolicyOverwrite;
		$this->assertFalse($fs->exists('does not exist'));

		$file1 = '/tmp/fstest.txt';
		$st = $fs->touch($file1);
		$this->assertTrue($st->isOK());
		$this->assertFileExists($st->source);

		$st = $fs->move($file1, $file1 . '.txt');
		$this->assertTrue($st->isOK());
		$this->assertFileExists($st->destination);

		$list = $fs->ls('/tmp');
		$this->assertTrue($list->contains($fs->localize($st->destination)));

		$st = $fs->delete($st->destination);
		$this->assertTrue($st->isOK());
		$this->assertFileNotExists($st->source);

		$st = $fs->delete($st->source);
		$this->assertFalse($st->isOK());
	}

	/**
	 * @depends testStart
	 * @param FileSystem $fs
	 */
	public function testFolderMethods(FileSystem $fs) {
		$st = $fs->mkdir('/tmp/test');
		$this->assertTrue($st->isOK());
		$this->assertDirectoryExists($st->source);

		$st = $fs->rmdir('/tmp/test');
		$this->assertTrue($st->isOK());
		$this->assertDirectoryNotExists($st->source);
	}

	/**
	 * @depends testStart
	 * @param FileSystem $fs
	 */
	public function testPermissionsMethods(FileSystem $fs) {
		$this->assertTrue((int)$fs->ownerId('/') > 0);
		$this->assertTrue(strlen($fs->ownerName('/')) > 0);
		$this->assertNotFalse($fs->permissions('/'));
		$this->assertNotFalse($fs->permissionsStr('/'));
	}

	/**
	 * @depends testStart
	 * @param FileSystem $fs
	 */
	public function testPredicateMethods(FileSystem $fs) {
		$this->assertTrue($fs->isDir('/'));
		$this->assertTrue($fs->isReadable('/'));

		$fs->touch('fstest.txt');
		$this->assertTrue($fs->isFile('fstest.txt'));
		$this->assertTrue($fs->isReadable('fstest.txt'));
		$this->assertTrue($fs->isWritable('fstest.txt'));
		$fs->delete('fstest.txt');
	}

	/**
	 * @depends testStart
	 * @param FileSystem $fs
	 */
	public function testDiskMethods(FileSystem $fs) {
		$this->assertTrue($fs->freeSpace() > 0);
		$this->assertTrue($fs->totalSpace() > 0);
	}

	/**
	 * @depends testStart
	 * @param FileSystem $fs
	 */
	public function testHelperMethods(FileSystem $fs) {
		$file = $fs->normalize('a.txt');
		$this->assertStringStartsWith(CMS::appPath(), $file);
		$file = $fs->localize($file);
		$this->assertEquals('/a.txt', $file);

		$fs->touch('fstest.txt');
		$this->assertStringEndsWith('fstest (2).txt', $fs->uniqueName('fstest.txt'));
		$fs->delete('fstest.txt');
	}
}
