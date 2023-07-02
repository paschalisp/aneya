<?php
/*
 * aneya CMS & Framework
 * Copyright (c) 2007-2022 Paschalis Pagonidis <p.pagonides@gmail.com>
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
 * Portions created by Paschalis Ch. Pagonidis are Copyright (c) 2007-2022
 * Paschalis Ch. Pagonidis. All Rights Reserved.
 */

namespace aneya\FileSystem;

use aneya\Core\CMS;
use aneya\Core\Data\IFilterable;
use aneya\Core\JsonCompatible;

class File implements IFilterable, \JsonSerializable {
	use JsonCompatible { jsonSerialize as traitJsonSerialize; }

	#region Constants
	const File			= 'file';
	const Directory		= 'directory';
	const Symlink		= 'symlink';
	#endregion

	#region Properties
	/** @var string */
	public $path;
	/** @var string */
	public $basename;
	/** @var string */
	public $filename;
	/** @var string */
	public $extension;
	/** @var string */
	public $icon;
	/** @var string */
	public $type;
	/** @var int */
	public $size;
	#endregion

	#region Constructor
	/**
	 * File constructor.
	 *
	 * @param string $file File's name including full path from application root directory.
	 */
	public function __construct(string $file) {
		$file = CMS::filesystem()->localize($file);
		$info = pathinfo($file);

		$this->path = $info['dirname'];
		$this->basename = $info['basename'];
		$this->filename = $info['filename'];
		$this->extension = $info['extension'];

		if ($this->basename == '.')
			$this->filename = '.';
		elseif ($this->basename == '..')
			$this->filename = '..';

		if ($this->exists()) {
			$this->size =  CMS::filesystem()->filesize($this->name());
			if (CMS::filesystem()->isDir($this->name()))
				$this->type = File::Directory;
			else
				$this->type = CMS::filesystem()->mimetype($this->name());
		}
		else {
			$this->size = 0;
			$this->type = MimeType::byExtension($this->extension);
		}
		$this->icon = $this->icon();
	}
	#endregion

	#region Methods
	/**
	 * Returns true if the file exists in the filesystem
	 */
	public function exists() {
		return CMS::filesystem()->exists($this->name());
	}

	/**
	 * Returns a link to file's icon PNG, depending its mime type.
	 * @return string
	 */
	public function icon() {
		$category = MimeType::category($this->type);
		switch ($category) {
			case MimeType::Directory:
			case File::Directory:
				switch ($this->extension) {
					default:
						$icon = "mime-folder.png";
						break;
				}
				break;

			case MimeType::Compressed:
				switch ($this->extension) {
					default:
						$icon = "mime-archive.png";
						break;
				}
				break;

			case MimeType::Audio:
				switch ($this->extension) {
					case 'mid':
						$icon = "mime-midi.png";
						break;
					default:
						$icon = "mime-audio.png";
						break;
				}
				break;

			case MimeType::Document:
				switch ($this->extension) {
					case 'pdf':
						$icon = "mime-pdf.png";
						break;
					case 'ppt':
					case 'pptx':
					case 'pps':
						$icon = "mime-presentation.png";
						break;
					default:
						$icon = "mime-document.png";
						break;
				}
				break;

			case MimeType::Executable:
				switch ($this->extension) {
					case 'bat':
					case 'sh':
						$icon = "mime-shellscript.png";
						break;

					default:
						$icon = "mime-executable.png";
						break;
				}
				break;

			case MimeType::Image:
				switch ($this->extension) {
					case 'svg':
					case 'svgz':
						$icon = "mime-draw.png";
						break;
					default:
						$icon = "mime-image.png";
						break;
				}
				break;

			case MimeType::SourceCode:
				switch ($this->extension) {
					case 'css':
						$icon = "mime-css.png";
						break;
					case 'html':
					case 'htm':
					case 'xhtml':
						$icon = "mime-html.png";
						break;
					case 'php':
					case 'phtml':
						$icon = "mime-php.png";
						break;
					case 'java':
					case 'jar':
						$icon = "mime-java.png";
						break;
					case 'rb':
						$icon = "mime-ruby.png";
						break;
					case 'pl':
					case 'sh':
						$icon = "mime-shellscript.png";
						break;
					case 'sql':
						$icon = "mime-sql.png";
						break;
					case 'xml':
					default:
						$icon = "mime-xml.png";
						break;
				}
				break;

			case MimeType::Spreadsheet:
				switch ($this->extension) {
					default:
						$icon = "mime-spreadsheet.png";
						break;
				}
				break;

			case MimeType::Text:
				switch ($this->extension) {
					default:
						$icon = "mime-text.png";
						break;
				}
				break;

			case MimeType::Video:
				switch ($this->extension) {
					default:
						$icon = "mime-video.png";
						break;
				}
				break;

			default:
				switch ($this->extension) {
					case 'db' :
					case 'mdb':
						$icon = "mime-database.png";
						break;
					case 'iso':
						$icon = "mime-dvd.png";
						break;
					case 'epub':
						$icon = "mime-epub.png";
						break;
					case 'ttf':
					case 'otf':
						$icon = "mime-font.png";
						break;
					case 'log':
						$icon = "mime-log.png";
						break;
					case 'msg':
						$icon = "mime-mail.png";
						break;

					default:
						$icon = "mime-empty.png";
				}
		}

		return "/modules/app/html/icons/mimetypes/$icon";
	}

	/**
	 * Returns a file's SHA1 hash.
	 * @return string
	 */
	public function hash() {
		if ($this->hash === null)
			$this->hash = sha1_file(CMS::filesystem()->normalize($this->name()));

		return $this->hash;
	}

	/**
	 * Returns the type of the file or directory, based on its mime type if it exists on the filesystem; otherwise based on its extension (files only)
	 * @param string $type If given, it will return true if the file is of the given type
	 * @return string|bool
	 */
	public function is($type = null) {
		if (CMS::filesystem()->isDir($this->name()))
			return ($type === self::Directory) ? true : ($type == null ? self::Directory : false);
		elseif (CMS::filesystem()->isLink($this->name()))
			return ($type === self::Symlink) ? true : ($type == null ? self::Symlink : false);

		return ($type === null)
			? MimeType::category($this->type)
			: ($type === self::File || $type == MimeType::category($this->type));
	}

	/**
	 * Returns true if File matches the given conditions.
	 * @param FileFilter|FileFilter[]|FileFilterCollection $filters
	 * @return bool
	 */
	public function match($filters) {
		if ($filters instanceof FileFilterCollection) {
			if ($filters->operand == FileFilterCollection::OperandAnd) {
				foreach ($filters as $filter) {
					if (!$this->match(($filter)))
						return false;
				}
			}
			elseif ($filters->operand == FileFilterCollection::OperandOr) {
				foreach ($filters as $filter) {
					if ($this->match(($filter)))
						return true;
				}
			}

			return false;
		}
		elseif ($filters instanceof FileFilter) {
			$f = $filters;

			switch ($f->property) {
				case FileFilter::BaseName:
					$property = $this->basename;
					break;
				case FileFilter::FileName:
					$property = $this->filename;
					break;
				case FileFilter::Name:
					$property = $this->name();
					break;
				case FileFilter::Extension:
					$property = pathinfo($this->basename, PATHINFO_EXTENSION);
					break;
				case FileFilter::Type:
					$property = $this->type;
					break;
				case FileFilter::Size:
					$property = $this->size;
					break;
				case FileFilter::Path:
					$property = $this->path;
					break;
				default:
					if (!in_array($f->condition, [FileFilter::NoFilter, FileFilter::FalseFilter, FileFilter::InFolder, FileFilter::NotInFolder, FileFilter::Exists])) {
						CMS::logger()->debug("Invalid File property $f->property");
						return false;
					}

					$property = null;
			}

			switch ($f->condition) {
				case FileFilter::NoFilter		: return true;
				case FileFilter::FalseFilter	: return false;
				case FileFilter::Equals			: return ($property === $f->value);
				case FileFilter::NotEqual		: return ($property !== $f->value);
				case FileFilter::LessThan		: return $f->property == FileFilter::Size && ($property < $f->value);
				case FileFilter::LessOrEqual	: return $f->property == FileFilter::Size && ($property <= $f->value);
				case FileFilter::GreaterThan	: return $f->property == FileFilter::Size && ($property > $f->value);
				case FileFilter::GreaterOrEqual	: return $f->property == FileFilter::Size && ($property >= $f->value);
				case FileFilter::Between		: return $f->property == FileFilter::Size && ($property >= $f->value[0] && $property <= $f->value[1]);
				case FileFilter::IsEmpty		: return (empty ($value));
				case FileFilter::NotEmpty		: return (!empty ($value));
				case FileFilter::StartsWith		: return ($f->value == '' || stripos ((string)$property, (string)$f->value) === 0);
				case FileFilter::EndsWith		: return ($f->value == '' || substr ((string)$property, -strlen ((string)$f->value)) == (string)$f->value);
				case FileFilter::NotStartWith	: return (stripos ((string)$property, (string)$f->value) !== 0);
				case FileFilter::NotEndWith		: return (substr ((string)$property, -strlen ((string)$f->value)) != (string)$f->value);
				case FileFilter::Contains		: return (stripos ((string)$property, (string)$f->value) !== false);
				case FileFilter::NotContain		: return (stripos ((string)$property, (string)$f->value) === false);
				case FileFilter::InList			: return is_array ($f->value) && in_array($property, $f->value);
				case FileFilter::NotInList		: return is_array ($f->value) && !in_array($property, $f->value);
				case FileFilter::Exists			: return CMS::filesystem()->exists($this->name());
				case FileFilter::NotExists		: return !CMS::filesystem()->exists($this->name());
				case FileFilter::InFolder		: return strpos(CMS::filesystem()->localize($this->name()), CMS::filesystem()->localize($f->value)) === 0;
				case FileFilter::NotInFolder	: return strpos(CMS::filesystem()->localize($this->name()), CMS::filesystem()->localize($f->value)) === false;
				default:
					CMS::logger()->debug("Invalid condition $f->condition");
					return false;
			}
		}
		elseif (is_array ($filters) && count($filters) > 0) {
			$collection = new FileFilterCollection();
			$collection->operand = FileFilterCollection::OperandAnd;
			$collection->addRange($filters);

			return $this->match($collection);
		}
		elseif ($filters == null)
			return true;

		return false;
	}

	/** Returns the full path plus the file's name in a string */
	public function name(): string {
		return $this->path . '/' . $this->basename;
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize(): array {
		$data = $this->traitJsonSerialize();

		#region Include custom properties, if set
		// Used to contain file signatures
		if (isset($this->hash))
			$data['hash'] = $this->hash;

		// Used to contain sub-folder contents
		if (isset($this->files) && $this->files instanceof FileCollection)
			$data['files'] = $this->files->jsonSerialize();
		#endregion

		return $data;
	}
	#endregion

	#region Static methods
	#endregion
}
