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

namespace aneya\M17N;

use aneya\Core\CMS;
use aneya\Core\Data\Database;
use aneya\Core\I18N;

final class M17N {
	#region Properties
	protected \stdClass $_cfg;

	/** @var Language[] */
	protected array $_languages = [];

	/** @var array[] */
	protected array $_translations = [];

	protected ?string $__m17n_language = '';

	/** @var M17N */
	private static M17N $_instance;
	#endregion

	#region Constructor
	private function __construct() {
		self::$_instance = $this;

		$this->_cfg = $cfg = CMS::modules()->cfg('aneya/m17n');
		$this->_languages = [];

		foreach ($cfg->languages as $code => $lang) {
			$this->_languages[$lang->code] = new Language($lang->code, $lang->abbr, $lang->name, $lang->locale);
		}
	}
	#endregion

	#region Languages methods
	/**
	 * Returns all activated languages.
	 * The result is an array of stdClass objects with the following properties: code, name, abbr, locale
	 *
	 * @return Language[]
	 */
	public function languages(): array {
		return $this->_languages;
	}

	/**
	 * Returns the default language that is set in application's main configuration,
	 * or in aneya/m17n module's configuration if it is not set in the application level.
	 *
	 * @return Language
	 */
	public function defaultLanguage(): Language {
		return $this->_languages[$this->_cfg->translations->defaultLanguage];
	}

	/** Returns the current language that is set/activated in the request, environment, or by default in the application. */
	public function currentLanguage(): Language {
		$code = CMS::session()->get('__m17n_language') ?? $this->__m17n_language;
		return $this->_languages[$code] ?? $this->defaultLanguage();
	}

	/** Sets the language, given by its code, as the current (active) for translation operations.
	 * Returns false if no such language is set in application's configuration. */
	public function setCurrentLanguage(string $code): self|bool {
		if (isset($this->_languages[$code])) {
			CMS::session()->set('__m17n_language', $code);
			$this->__m17n_language = $code;

			if (I18N::getCurrentLocale() !== $this->_languages[$code]->locale)
				I18N::setLocale($this->_languages[$code]->locale);
		}
		else
			return false;

		return $this;
	}

	/** Returns true if there is a language set in application's configuration with the given code. */
	public function isLanguageEnabled(string $code): bool {
		return isset($this->_languages[$code]);
	}
	#endregion

	#region Translation methods
	/**
	 * Returns a hash array containing all translations found in section for the current language code.
	 *
	 * @param string $section
	 *
	 * @return string[]
	 */
	public function all(string $section): array {
		$section = $this->normalize($section);

		if (isset($this->_translations[$section]))
			return $this->_translations[$section];

		$lang = $this->currentLanguage();
		$schema = CMS::db()->getSchemaName();
		$sql = "SELECT T1.tag, T1.value
				FROM $schema.cms_translationsTr T1
				JOIN $schema.cms_translations T2 ON T2.tag=T1.tag AND T2.section=T1.section AND T1.language_code=:lang
				WHERE T2.section=:section";
		$rows = CMS::db()->fetchAll($sql, [':lang' => $lang->code, ':section' => $section]);

		$trans = [];
		if ($rows)
			foreach ($rows as $row)
				$trans[strtolower($row['tag'])] = $row['value'];

		$this->_translations[$section] = $trans;

		return $trans;
	}

	/** Normalizes a translation tag by stripping all non-alphanumeric characters, excluding dot, dash and low dash. */
	public function normalize(string $tag): string {
		// Strip all non-alphanumeric, excluding dot, dash and low dash
		$tag = preg_replace('/[^a-zA-Z0-9\s.\-_]/', ' ', $tag);

		// Strip multiple spaces
		return strtolower(preg_replace('/\s+/', ' ', trim($tag)));
	}

	/** Returns the translation of the given phrase. */
	public function translate(string $tag, string $section, string $defaultTranslation = null, string $lang = null): string {
		$defaultTranslation = $defaultTranslation ?? $tag;
		$tag = $this->normalize($tag);
		$section = $this->normalize($section);

		if (!isset($this->_translations[$section]))
			$this->all($section);

		if (isset($this->_translations[$section][$tag]))
			return $this->_translations[$section][$tag];
		else {
			// Add the translation to the translations table,
			// assuming that the default translation is written in the default application language
			$lang = $lang ?? $this->defaultLanguage()->code;
			$this->store($tag, $section, $defaultTranslation, $lang);
			return $defaultTranslation;
		}
	}

	/** Stores the translation information into the database. */
	public function store(string $tag, string $section, string $value = null, string $lang = 'en'): M17N {
		try {
			$schema = CMS::db()->getSchemaName();
			$tag = $this->normalize($tag);
			$section = $this->normalize($section);

			if (CMS::db()->getDriverType() === Database::PostgreSQL) {
				$sql1 = "INSERT INTO $schema.cms_translations (tag, section) VALUES (:tag, :section) ON CONFLICT (tag, section) DO NOTHING";
				$sql2 = "INSERT INTO $schema.cms_translationsTr (tag, section, language_code, value) VALUES (:tag, :section, :language_code, :value)
					ON CONFLICT (tag, section, language_code) DO UPDATE SET value=COALESCE (EXCLUDED.value, cms_translationsTr.value)";
			}
			else {
				$sql1 = "INSERT IGNORE INTO $schema.cms_translations (tag, section) VALUES (:tag, :section)";
				$sql2 = "INSERT INTO $schema.cms_translationsTr (tag, section, language_code, `value`) VALUES (:tag, :section, :language_code, :value)
					ON DUPLICATE KEY UPDATE `value`=IFNULL(`value`, VALUES(`value`))";
			}
			$ret = CMS::db()->execute($sql1, [':tag' => $tag, ':section' => $section]);
			if ($ret) {
				CMS::db()->execute($sql2, [
					':tag'				=> $tag,
					':section'			=> $section,
					':language_code'	=> $lang,
					':value'			=> $value
				]);

				$this->_translations[$section][$tag] = $value;
			}
		}
		catch (\Exception $e) { }

		return $this;
	}
	#endregion

	#region Static methods
	/** Returns multilingualization module's core class instance. */
	public static function instance(): M17N {
		return  (!isset(self::$_instance)) ? new M17N() : self::$_instance;
	}
	#endregion
}
