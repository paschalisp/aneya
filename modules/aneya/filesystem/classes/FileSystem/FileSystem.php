<?php
/*
 * aneya CMS & Framework
 * Copyright (c) 2011-2022 Paschalis Pagonidis <p.pagonides@gmail.com>
 * All rights reserved.
 * -----------------------------------------------------------------------------
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 * -----------------------------------------------------------------------------
 * The Sole Developer of the Original Code is Paschalis Ch. Pagonidis
 * Portions created by Paschalis Ch. Pagonidis are Copyright (C) 2011-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\FileSystem;

use aneya\Core\Application;
use aneya\Core\ApplicationError;
use aneya\Core\CMS;
use aneya\Core\Environment\Process;
use aneya\Core\ErrorEventArgs;
use aneya\Core\EventStatus;

class FileSystem {
	#region Constants
	/** Cancel the process if a file with the same name exists */
	const OverwritePolicyCancel		= 1;
	/** Overwrite any existing file with the same name */
	const OverwritePolicyOverwrite	= 2;
	/** Rename automatically the new file if a file with the same name exists */
	const OverwritePolicyRename		= 3;

	const CmdCopy					= 'cp';
	const CmdDelete					= 'rm';
	const CmdRename					= 'mv';
	const CmdWrite					= 'write';
	const CmdMove					= 'mv';
	const CmdMoveUpload				= 'mvu';
	const CmdTouch					= 'touch';
	const CmdUnlink					= 'rm';
	const CmdChdir					= 'cd';
	const CmdChgrp					= 'chgrp';
	const CmdChmod					= 'chmod';
	const CmdChown					= 'chown';
	const CmdChroot					= 'chroot';
	const CmdMkdir					= 'mkdir';
	const CmdRmdir					= 'rmdir';
	const CmdSymlink				= 'symlink';
	const CmdTempnam				= 'tempnam';

	const AppRoot					= 'A';
	const WebRoot					= 'W';
	#endregion

	#region Properties
	/** @var int Defines the overwrite policy considered in all filesystem functions, such as copy(), moveUploadedFile() etc.) */
	public int $overwritePolicy = self::OverwritePolicyOverwrite;

	/** @var \Throwable The last error */
	protected ?\Throwable $lastError = null;

	/** @var string Current working directory. Defaults to application's root directory. */
	protected string $_dir;

	/** @var array[] */
	protected static array $_trail = [];
	#endregion

	#region Constructor
	public function __construct() {
		$this->_dir = CMS::appPath();
	}
	#endregion

	#region File methods
	/**
	 * @see copy()
	 * @param string    $source
	 * @param string    $destination
	 * @param resource  $context
	 *
	 * @return FileSystemStatus
	 */
	public function copy(string $source, string $destination, $context = null): FileSystemStatus {
		$status = new FileSystemStatus();

		$source = $this->normalize($source);
		$destination = $this->normalize($destination);

		$status->command = self::CmdCopy;
		$status->source = $source;
		$status->destination = $destination;

		#region If a directory was given as a destination, suffix the destination with source's filename
		if (is_dir($destination)) {
			$filename = pathinfo($source, PATHINFO_BASENAME);
			if (!$this->endsWith($destination, '/'))
				$destination .= '/';

			$status->destination = ($destination .= $filename);
		}
		#endregion

		#region If destination already exists, check overwrite policy
		if (file_exists($destination)) {
			switch ($this->overwritePolicy) {
				case self::OverwritePolicyCancel:
					$status->isPositive = false;
					$status->message = 'Failed to copy file. Destination already exists.';
					$status->debugMessage = "Failed to copy file $source to $destination. Destination already exists";
					return $status;

				case self::OverwritePolicyRename:
					$status->destination = $destination = $this->uniqueName($destination);
					break;
			}
		}
		#endregion

		#region If destination directory does not exist, try to create it
		$path = pathinfo($destination, PATHINFO_DIRNAME);
		if (!is_dir($path)) {
			if (func_num_args() > 2)
				$ret = $this->mkdir($path, 0777, true, $context);
			else
				$ret = $this->mkdir($path, 0777, true);

			if ($ret->isError()) {
				$status->isPositive = false;
				$status->message = 'Failed to copy file. Destination folder could not be created.';
				$status->debugMessage = "Failed to copy file $source to $destination. Destination folder could not be created";
				return $status;
			}
		}
		#endregion

		if (func_num_args() > 2)
			$ret = copy($source, $destination, $context);
		else
			$ret = copy($source, $destination);
		if ($ret === true)
			$this->trail($status->command, "copy($source, $destination)");
		else {
			$status->isPositive = false;
			$status->message = 'Failed to copy file to the destination';
			$status->debugMessage = "Failed to copy file $source to $destination";
		}

		return $status;
	}

	/**
	 * @see unlink()
	 * @param string $filename
	 *
	 * @return FileSystemStatus
	 */
	public function delete(string $filename): FileSystemStatus {
		return $this->unlink($filename);
	}

	/**
	 * @see file_exists()
	 * @param string $filename
	 *
	 * @return bool
	 */
	public function exists(string $filename): bool {
		$filename = $this->normalize($filename);

		return file_exists($filename);
	}

	/** Returns a File instance representing the given file. */
	public function file(string $filename): File {
		$filename = $this->localize($filename);

		return new File($filename);
	}

	/** @see filesize() */
	public function filesize(string $filename): int {
		$filename = $this->normalize($filename);

		return filesize($filename);
	}

	/** @see filetype() */
	public function filetype(string $filename): string {
		$filename = $this->normalize($filename);

		return filetype($filename);
	}

	/** @see hash_file() */
	public function hash(string $filename, string $algo = 'sha1'): string {
		$filename = $this->normalize($filename);

		return hash_file($algo, $filename);
	}

	/**
	 * Returns the mime type of the given file.
	 *
	 * @param string $filename
	 *
	 * @return string|string[]|bool|null
	 */
	public function mimetype(string $filename): string|array|bool|null {
		$filename = $this->normalize($filename);

		if ($this->exists($filename)) {
			if (function_exists("finfo_file")) {
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$type = finfo_file($finfo, $filename);
				finfo_close($finfo);
			}
			else {
				$type = mime_content_type($filename);
			}
		}
		else {
			$ext = pathinfo($filename, PATHINFO_EXTENSION);
			$type = MimeType::byExtension($ext);
		}

		return $type;
	}

	/**
	 * @see linkinfo()
	 * @param string $path
	 *
	 * @return int|bool
	 */
	public function linkinfo(string $path): int|bool {
		$path = $this->normalize($path);

		return linkinfo($path);
	}

	/**
	 * @see file_get_contents()
	 * @param string $filename
	 *
	 * @return bool|string
	 */
	public function read(string $filename): bool|string {
		$filename = $this->normalize($filename);

		return @file_get_contents($filename);
	}

	/**
	 * @see file_put_contents()
	 * @param string $filename
	 * @param string $content
	 * @param int    $flags
	 *
	 * @return FileSystemStatus
	 */
	public function write(string $filename, $content, $flags = null): FileSystemStatus {
		$filename = $this->normalize($filename);

		if ($flags !== null)
			$ret = @file_put_contents($filename, $content, $flags);
		else
			$ret = @file_put_contents($filename, $content);

		$status = new FileSystemStatus();

		$status->command = self::CmdWrite;
		$status->destination = $filename;
		if ($ret === false) {
			$status->isPositive = false;
			$status->message = 'Failed to write contents to file.';
			$status->debugMessage = "Failed to write content to $filename.";
		}
		else {
			$status->data = $ret;
		}

		return $status;
	}

	/**
	 * @see rename()
	 * @param string $source
	 * @param string $destination
	 * @param null   $context
	 *
	 * @return FileSystemStatus
	 */
	public function rename(string $source, string $destination, $context = null): FileSystemStatus {
		$status = new FileSystemStatus();

		$source = $this->normalize($source);
		$destination = $this->normalize($destination);

		$status->command = self::CmdRename;
		$status->source = $source;
		$status->destination = $destination;

		// If a directory was given as a destination (but source is a file), suffix the destination with source's filename
		if (is_dir($destination) && !is_dir($source)) {
			$filename = pathinfo($source, PATHINFO_BASENAME);
			if (!$this->endsWith($destination, '/'))
				$destination .= '/';
			$status->destination = ($destination .= $filename);
		}

		#region If destination already exists, check overwrite policy
		if (file_exists($destination)) {
			switch ($this->overwritePolicy) {
				case self::OverwritePolicyCancel:
					$status->isPositive = false;
					$status->message = 'Failed to copy file. Destination already exists.';
					$status->debugMessage = "Failed to copy file $source to $destination. Destination already exists";
					return $status;

				case self::OverwritePolicyRename:
					$status->destination = $destination = $this->uniqueName($destination);
					break;
			}
		}
		#endregion

		if (func_num_args() > 2)
			$ret = @rename($source, $destination, $context);
		else
			$ret = @rename($source, $destination);
		if ($ret === true)
			$this->trail($status->command, "rename($source, $destination)");
		else {
			$status->isPositive = false;
			$status->message = 'Failed to move file or directory';
			$status->debugMessage = "Failed to move or directory $source to $destination";
		}

		return $status;
	}

	/**
	 * @see rename()
	 * @param string $source
	 * @param string $destination
	 * @param null   $context
	 *
	 * @return FileSystemStatus
	 */
	public function move(string $source, string $destination, $context = null): FileSystemStatus {
		if (func_num_args() > 2)
			return $this->rename($source, $destination, $context);
		else
			return $this->rename($source, $destination);
	}

	/**
	 * @see stat()
	 * @param string $filename
	 *
	 * @return array|bool
	 */
	public function stat(string $filename): bool|array {
		$filename = $this->normalize($filename);

		return stat($filename);
	}

	/**
	 * @see symlink()
	 * @param string $target
	 * @param string $link
	 *
	 * @return FileSystemStatus
	 */
	public function symlink(string $target , string $link): FileSystemStatus {
		$status = new FileSystemStatus();

		$target = $this->normalize($target);
		$link = $this->normalize($link);

		// If a directory was given as a destination, suffix the destination with source's filename
		if (is_dir($link)) {
			$filename = pathinfo($target, PATHINFO_BASENAME);
			if (!$this->endsWith($link, '/'))
				$link .= '/';
			$link .= $filename;
		}

		if (file_exists($link)) {
			switch ($this->overwritePolicy) {
				case self::OverwritePolicyCancel:
					$this->lastError = new FileSystemStatus();
					break;
			}
		}

		$status->command = self::CmdSymlink;
		$status->source = $target;
		$status->destination = $link;

		$ret = @symlink($target, $link);
		if ($ret === true)
			$this->trail($status->command, "symlink($target, $link)");
		else {
			$status->isPositive = false;
			$status->message = 'Failed to create symbolic link';
			$status->debugMessage = "Failed to create symbolic link of $target to $link";
		}

		return $status;
	}

	/**
	 * @see tempnam()
	 * @param string $dir
	 * @param string $prefix
	 *
	 * @return bool|string
	 */
	public function tempnam(string $dir, string $prefix): bool|string {
		$dir = $this->normalize($dir);

		$ret = @tempnam($dir , $prefix);
		if ($ret !== false)
			$this->trail(self::CmdTempnam, "tempnam($dir, $prefix)");

		return $ret;
	}

	/**
	 * @see touch()
	 * @param string   $filename
	 * @param int|null $time
	 * @param int|null $atime
	 *
	 * @return FileSystemStatus
	 */
	public function touch(string $filename, int $time = null, int $atime = null): FileSystemStatus {
		$status = new FileSystemStatus();

		$filename = $this->normalize($filename);

		$status->command = self::CmdTouch;
		$status->source = $filename;
		$status->destination = $filename;
		$ret = @touch($filename, $time, $atime);
		if ($ret === true)
			$this->trail($status->command, "touch($filename, $time, $atime)");
		else {
			$status->isPositive = false;
			$status->message = "Failed to touch '$filename'";
		}

		return $status;
	}

	/**
	 * @see unlink()
	 * @param string $filename
	 * @param null   $context
	 *
	 * @return FileSystemStatus
	 */
	public function unlink(string $filename, $context = null): FileSystemStatus {
		$status = new FileSystemStatus();
		$this->lastError = null;

		$filename = $this->normalize($filename);

		$status->source = $filename;
		$status->command = self::CmdUnlink;

		CMS::app()->on(Application::EventOnError, $f = function (ErrorEventArgs $args) {
			if ($args->error->getCode() == E_WARNING) {
				$this->lastError = $args->error;
				return new EventStatus(true, $args->error->getMessage(), $args->error->getCode(), null, true);
			}

			return null;
		});
		if (func_num_args() > 2)
			$ret = @unlink($filename, $context);
		else
			$ret = @unlink($filename);
		CMS::app()->off(Application::EventOnError, $f);

		if ($ret === true)
			$this->trail($status->command, "unlink($filename)");
		else {
			$status->isPositive = false;
			$status->message = 'Failed to delete file';

			/** @var ApplicationError $error */
			$error = CMS::app()->errors->last();
			$status->debugMessage = "Failed to delete file $filename. " . $error?->getMessage();

			if ($this->lastError instanceof \Throwable)
				$status->debugMessage .= '. ' . $this->lastError->getMessage();
		}

		return $status;
	}
	#endregion

	#region Directory methods
	/**
	 * @see chdir()
	 * @param string $directory
	 *
	 * @return FileSystemStatus
	 */
	public function chdir(string $directory): FileSystemStatus {
		$status = new FileSystemStatus();
		$directory = $this->normalize($directory);

		$status->command = self::CmdChdir;
		$status->source = $status->destination = $directory;

		$ret = @chdir($directory);
		if ($ret === true) {
			$this->_dir = $directory;
			$this->trail($status->command, "chdir($directory)");
		}
		else {
			$status->message = 'Failed to change to folder';
			$status->debugMessage = "Failed to change to folder $directory";
		}

		return $status;
	}

	/**
	 * @see chroot()
	 * @param string $directory
	 *
	 * @return FileSystemStatus
	 */
	public function chroot(string $directory): FileSystemStatus {
		$status = new FileSystemStatus();
		$directory = $this->normalize($directory);

		$status->command = self::CmdChroot;
		$status->source = $status->destination = $directory;

		$ret = @chroot($directory);
		if ($ret === true) {
			$this->_dir = $directory;
			$this->trail($status->command, "chroot($directory)");
		}
		else {
			$status->message = 'Failed to chroot to folder';
			$status->debugMessage = "Failed to chroot to folder $directory";
		}

		return $status;
	}

	/**
	 * Returns the size (in bytes) of the given directory.
	 * @param string $directory
	 *
	 * @return int
	 */
	public function dirsize(string $directory): int {
		$p = Process::cmd($path = sprintf('du -sb %s', $this->normalize($directory)))->wait();
		[$size, $path] = explode("\t", $p->output());

		return (int)$size;
	}

	/**
	 * @see getcwd()
	 * @return string|bool
	 */
	public function getcwd(): string|bool {
		return getcwd();
	}

	/**
	 * @see mkdir()
	 * @param string $directory
	 * @param int    $mode
	 * @param bool   $recursive
	 * @param resource $context
	 *
	 * @return FileSystemStatus
	 */
	public function mkdir(string $directory, int $mode = 0777 ,bool $recursive = false, $context = null): FileSystemStatus {
		$status = new FileSystemStatus();
		$this->lastError = null;

		$directory = $this->normalize($directory);

		$status->command = self::CmdMkdir;
		$status->source = $status->destination = $directory;

		CMS::app()->on(Application::EventOnError, $f = function (ErrorEventArgs $args) {
			if ($args->error->getCode() == E_WARNING) {
				$this->lastError = $args->error;
				return new EventStatus(true, $args->error->getMessage(), $args->error->getCode(), null, true);
			}

			return null;
		});
		if (func_num_args() > 3)
			$ret = @mkdir($directory, $mode, $recursive, $context);
		else
			$ret = @mkdir($directory, $mode, $recursive);
		CMS::app()->off(Application::EventOnError, $f);

		if ($ret === true)
			$this->trail($status->command, "mkdir($directory, $mode, $recursive)");
		else {
			$status->isPositive = false;
			$status->message = 'Failed to create folder';
			$status->debugMessage = "Failed to create folder $directory" . ($recursive ? ' recursively' : '');

			if ($this->lastError instanceof \Throwable)
				$status->debugMessage .= '. ' . $this->lastError->getMessage();
		}

		return $status;
	}

	/**
	 * @param string $directory
	 * @param bool   $recursive
	 * @param null   $context
	 *
	 * @return FileSystemStatus
	 *@see rmdir()
	 */
	public function rmdir(string $directory, bool $recursive = false, $context = null): FileSystemStatus {
		$status = new FileSystemStatus();
		$this->lastError = null;

		$directory = $this->normalize($directory);

		$status->source = $directory;
		$status->command = self::CmdRmdir;

		CMS::app()->on(Application::EventOnError, $f = function (ErrorEventArgs $args) {
			if ($args->error->getCode() == E_WARNING) {
				$this->lastError = $args->error;
				return new EventStatus(true, $args->error->getMessage(), $args->error->getCode(), null, true);
			}

			return null;
		});
		if ($recursive === true) {
			$ret = $this->deltree($directory);
		}
		else {
			if (func_num_args() > 1)
				$ret = @rmdir($directory, $context);
			else
				$ret = @rmdir($directory);
		}
		CMS::app()->off(Application::EventOnError, $f);

		if ($ret === true)
			$this->trail($status->command, "rmdir($directory)");
		else {
			$status->isPositive = false;
			$status->message = 'Failed to delete folder';
			$status->debugMessage = "Failed to delete folder $directory";

			if ($this->lastError instanceof \Throwable)
				$status->debugMessage .= '. ' . $this->lastError->getMessage();
		}

		return $status;
	}

	/**
	 * Returns all contents of the given directory.
	 * @param string $directory
	 * @param bool If true, 'hash' dynamic property will contain each file's signature
	 * @param bool If true, 'files' dynamic property (an instance of FileCollection) will contain each directory's contents, hierarchically
	 *
	 * @return FileCollection
	 */
	public function ls(string $directory, bool $calcHashes = false, bool $deep = false): FileCollection {
		$col = new FileCollection();

		$directory = $this->normalize($directory);

		$dir = @opendir($directory);
		while (false != ($file = @readdir($dir))) {
			if ($file === '.')
				continue;

			$col->add(new File("$directory/$file"));
		}

		closedir($dir);

		if ($calcHashes) {
			foreach ($col->files()->all() as $file)
				// Calculate file's hash
				$file->hash();
		}

		if ($deep) {
			foreach ($col->folders()->all() as $dir) {
				if ($dir->filename === '.' || $dir->filename === '..')
					continue;

				$dir->files = $this->ls($dir->name(), $calcHashes, $deep);
			}
		}

		return $col->sort();
	}
	#endregion

	#region Permissions methods
	/**
	 * @see chgrp()
	 * @param string $filename
	 * @param        $group
	 *
	 * @return FileSystemStatus
	 */
	public function chgrp(string $filename, $group): FileSystemStatus {
		$status = new FileSystemStatus();
		$filename = $this->normalize($filename);

		$status->command = self::CmdChgrp;
		$status->source = $status->destination = $filename;

		$ret = @chgrp($filename, $group);
		if ($ret === true)
			$this->trail($status->command, "chgrp($filename)");
		else {
			$status->message = 'Failed to change file group';
			$status->debugMessage = "Failed to change file group ($group) on file $filename";
		}

		return $status;
	}

	/**
	 * @see chmod()
	 * @param string $filename
	 * @param int    $mode
	 *
	 * @return FileSystemStatus
	 */
	public function chmod(string $filename, int $mode): FileSystemStatus {
		$status = new FileSystemStatus();
		$filename = $this->normalize($filename);

		$status->command = self::CmdChmod;
		$status->source = $status->destination = $filename;

		$ret = @chmod($filename, $mode);
		if ($ret === true)
			$this->trail($status->command, "chmod($filename)");
		else {
			$status->message = 'Failed to change file permissions';
			$status->debugMessage = "Failed to change file permissions ($mode) on file $filename";
		}

		return $status;
	}

	/**
	 * @see chown()
	 * @param string $filename
	 * @param        $user
	 *
	 * @return FileSystemStatus
	 */
	public function chown(string $filename , $user): FileSystemStatus {
		$status = new FileSystemStatus();
		$filename = $this->normalize($filename);

		$status->command = self::CmdChown;
		$status->source = $status->destination = $filename;

		$ret = @chown($filename, $user);
		if ($ret === true)
			$this->trail($status->command, "chown($filename)");
		else {
			$status->message = 'Failed to change file ownership';
			$status->debugMessage = "Failed to change file owner ($user) on file $filename";
		}

		return $status;
	}

	/**
	 * @see fileowner()
	 * @param string $filename
	 *
	 * @return bool|int
	 */
	public function ownerId(string $filename): bool|int {
		$filename = $this->normalize($filename);

		return fileowner($filename);
	}

	/**
	 * @see fileowner()
	 * @param string $filename
	 *
	 * @return string|bool
	 */
	public function ownerName(string $filename): bool|string {
		$id = $this->ownerId($filename);

		if ($id !== false) {
			$ret = posix_getpwuid(fileowner($filename));
			return (isset($ret['name'])) ? $ret['name'] : false;
		}
		else
			return false;
	}

	/**
	 * @see fileperms()
	 * @param string $filename
	 * @return bool|int
	 */
	public function permissions(string $filename): bool|int {
		$filename = $this->normalize($filename);

		return fileperms($filename);
	}

	/**
	 * @see fileperms()
	 * @param string $filename
	 *
	 * @return bool|string
	 */
	public function permissionsStr(string $filename): bool|string {
		$perms = $this->permissions($filename);
		if ($perms === false)
			return false;

		$str = match ($perms & 0xF000) {
			0xC000 => 's',
			0xA000 => 'l',
			0x8000 => 'r',
			0x6000 => 'b',
			0x4000 => 'd',
			0x2000 => 'c',
			0x1000 => 'p',
			default => 'u',
		};

		// User
		$str .= (($perms & 0x0100) ? 'r' : '-');
		$str .= (($perms & 0x0080) ? 'w' : '-');
		$str .= (($perms & 0x0040) ?
			(($perms & 0x0800) ? 's' : 'x' ) :
			(($perms & 0x0800) ? 'S' : '-'));

		// Group
		$str .= (($perms & 0x0020) ? 'r' : '-');
		$str .= (($perms & 0x0010) ? 'w' : '-');
		$str .= (($perms & 0x0008) ?
			(($perms & 0x0400) ? 's' : 'x' ) :
			(($perms & 0x0400) ? 'S' : '-'));

		// Others
		$str .= (($perms & 0x0004) ? 'r' : '-');
		$str .= (($perms & 0x0002) ? 'w' : '-');
		$str .= (($perms & 0x0001) ?
			(($perms & 0x0200) ? 't' : 'x' ) :
			(($perms & 0x0200) ? 'T' : '-'));

		return $str;
	}
	#endregion

	#region Predicate methods
	/**
	 * @see is_dir
	 * @param string $filename
	 *
	 * @return bool
	 */
	public function isDir(string $filename): bool {
		$filename = $this->normalize($filename);

		return is_dir($filename);
	}

	/**
	 * @see is_executable()
	 * @param string $filename
	 *
	 * @return bool
	 */
	public function isExecutable(string $filename): bool {
		$filename = $this->normalize($filename);

		return is_executable($filename);
	}

	/**
	 * @see is_file()
	 * @param string $filename
	 *
	 * @return bool
	 */
	public function isFile(string $filename): bool {
		$filename = $this->normalize($filename);

		return is_file($filename);
	}

	/**
	 * @see is_link()
	 * @param string $filename
	 *
	 * @return bool
	 */
	public function isLink(string $filename): bool {
		$filename = $this->normalize($filename);

		return is_link($filename);
	}

	/**
	 * @see is_readable()
	 * @param string $filename
	 *
	 * @return bool
	 */
	public function isReadable(string $filename): bool {
		$filename = $this->normalize($filename);

		return is_readable($filename);
	}

	/**
	 * @see is_uploaded_file()
	 *
	 * Path normalization does not occur in this method
	 * @param string $filename
	 *
	 * @return bool
	 */
	public function isUploadedFile(string $filename): bool {
		return is_uploaded_file($filename);
	}

	/**
	 * @see is_writable()
	 * @param string $filename
	 *
	 * @return bool
	 */
	public function isWritable(string $filename): bool {
		$filename = $this->normalize($filename);

		return is_writable($filename);
	}
	#endregion

	#region Disk methods
	/**
	 * @see disk_free_space()
	 * @param string $directory
	 *
	 * @return bool|float
	 */
	public function freeSpace(string $directory = '/'): float|bool {
		return disk_free_space($directory);
	}

	/**
	 * @see disk_total_space()
	 * @param string $directory
	 *
	 * @return bool|float
	 */
	public function totalSpace(string $directory = '/'): float|bool {
		return disk_total_space($directory);
	}
	#endregion

	#region Uploading methods
	/**
	 * Outputs the given file forcing the browser to download, or embed inline if openInline argument is set accordingly.
	 * Callers should terminate immediately the application upon successful transmission.
	 *
	 * @param string|File $file Local full path to file to be downloaded.
	 * @param ?string $filename (optional) A name to serve the file as.
	 * @param bool $openInline (optional) If true, the file will be displayed embedded (content disposition inline) instead of attachment (content disposition attachment).
	 *
	 * @return FileSystemStatus
	 */
	public function download(File|string $file, string $filename = null, bool $openInline = false): FileSystemStatus {
		if (!($file instanceof File))
			$file = new File($file);

		if ($filename == null) {
			$filename = $file->basename;
		}

		if ($file->exists()) {
			header('Content-Description: File Transfer');
			header('Content-Type: ' . $file->type);
			header('Content-Disposition: ' . ($openInline ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
			header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
			header('Access-Control-Allow-Credentials: true');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . $file->size);

			readfile($this->normalize($file->name()));

			$status = new FileSystemStatus(true);
			$status->source = $file;
			$status->destination = $filename;
		}
		else
			return new FileSystemStatus(false, 'File does not exist', 0, sprintf('File "%s" does not exist inside the application directory.', $file->name()));

		return $status;
	}

	/**
	 * @see move_uploaded_file()
	 *
	 * @param string $filename The temporary filename generated by PHP used to pass to move_uploaded_file
	 * @param string $destination
	 *
	 * @return FileSystemStatus
	 */
	public function upload(string $filename, string $destination): FileSystemStatus {
		$status = new FileSystemStatus();

		$status->command = self::CmdMoveUpload;
		$status->source = $filename;
		$status->destination = $destination = $this->normalize($destination);

		#region If a directory was given as a destination, suffix the destination with source's filename
		if (is_dir($destination)) {
			$fname = pathinfo($filename, PATHINFO_BASENAME);
			if (!$this->endsWith($destination, '/'))
				$destination .= '/';
			$status->destination = ($destination .= $fname);
		}
		#endregion

		#region If destination already exists, check overwrite policy
		if (file_exists($destination)) {
			switch ($this->overwritePolicy) {
				case self::OverwritePolicyCancel:
					$status->isPositive = false;
					$status->message = 'Failed to move uploaded file. Destination already exists.';
					$status->debugMessage = "Failed to move uploaded file $filename to $destination. Destination already exists";
					return $status;

				case self::OverwritePolicyRename:
					$status->destination = $destination = $this->uniqueName($destination);
					break;
			}
		}
		#endregion

		#region If destination directory does not exist, try to create it
		$path = pathinfo($destination, PATHINFO_DIRNAME);
		if (!is_dir($path)) {
				$ret = $this->mkdir($path, 0777, true);

			if ($ret->isError()) {
				$status->isPositive = false;
				$status->message = 'Failed to move uploaded file. Destination folder could not be created.';
				$status->debugMessage = "Failed to move uploaded file $filename to $destination. Destination folder could not be created";
				return $status;
			}
		}
		#endregion

		$ret = @move_uploaded_file($filename, $destination);
		if ($ret === true)
			$this->trail($status->command, "move_uploaded_file($filename, $destination)");
		else {
			$status->isPositive = false;
			$status->message = 'Failed to move uploaded file to the destination';
			$status->debugMessage = "Failed to move uploaded file $filename to $destination";
		}

		return $status;
	}
	#endregion

	#region Helper methods
	/**
	 * Returns a normalized version of the given path or filename that starts with application's full path on the filesystem.
	 * @param string $path
	 *
	 * @return string
	 */
	public function normalize(string $path): string {
		if (strlen($path) == 0)
			return $path;

		if (!$this->startsWith($path, '/'))
			$path = '/' . $path;

		if (!$this->startsWith($path, $root = CMS::appPath()))
			$path = $root . $path;

		return $path;
	}

	/**
	 * Returns a localized version of the given (full) path or filename relative to application's root path.
	 * @param string $path
	 * @param string $root The root path to consider. Available values are FileSystem::AppRoot|WebRoot
	 *
	 * @return string
	 */
	public function localize(string $path, string $root = FileSystem::AppRoot): string {
		if (strlen($path) == 0)
			return $path;

		$path = $this->normalize($path);
		return (substr($path, strlen(($root == self::WebRoot) ? CMS::webPath() : CMS::appPath())));
	}

	/**
	 * Generates a unique name to an existing filename by adding an incrementing number at the end of the file name (e.g. "file (2).txt")
	 * @param string $filename
	 *
	 * @return string
	 */
	public function uniqueName(string $filename): string {
		$filename = $this->normalize($filename);
		$info = pathinfo($filename);

		$num = 2;
		while (file_exists($filename)) {
			$filename = $info['dirname'] . '/' . $info['filename'] . ' (' . $num++ . ').' . $info['extension'];
		}

		return $filename;
	}
	#endregion

	#region Internal methods
	/**
	 * Logs a filesystem command to the trail log
	 *
	 * @param string $method
	 * @param string $command
	 * @param ?string $comments (optional) Comments, such as a command result
	 *
	 * @return FileSystem
	 */
	protected function trail(string $method, string $command, string $comments = null): FileSystem {
		static::$_trail[] = ['m' => $method, 'c' => $command, '_' => $comments];

		return $this;
	}

	protected function startsWith(string $haystack, string $needle): bool {
		return strpos($haystack, $needle) === 0;
	}

	protected function endsWith(string $haystack, string $needle): bool {
		return substr($haystack, -strlen($needle)) === $needle;
	}

	protected function deltree(string $directory): bool {
		$handle = opendir($directory);
		while (($file = readdir($handle)) !== false) {
			if ($file == '.' || $file == '..')
				continue;

			$f = "$directory/$file";
			if (is_dir($f))
				$this->deltree($f);
			else
				unlink($f);
		}

		closedir($handle);
		rmdir($directory);

		return true;
	}
	#endregion

	#region Static methods
	/**
	 * Outputs the given file forcing the browser to download.
	 * Callers should terminate immediately the application upon successful transmission.
	 */
	public static function outputFile(string $file, string $filename = null): bool {
		if ($filename == null) {
			$filename = basename($file);
		}

		if (file_exists($file)) {
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . filesize($file));

			readfile($file);

			return true;
		}

		return false;
	}
	#endregion
}
