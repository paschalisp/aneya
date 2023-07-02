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

namespace aneya\Snippets;

use aneya\Core\ApplicationError;
use aneya\Core\Cache;
use aneya\Core\CMS;
use aneya\Core\Collection;
use aneya\Core\CoreObject;
use aneya\Core\EventArgs;
use aneya\Core\Hook;
use aneya\Core\IRenderable;
use aneya\Core\Parameters;
use aneya\Core\Utils\JavascriptUtils;

class Snippet extends CoreObject implements IRenderable {
	#region Constants
	/** Compiles the Snippet by including the file that was used to load its content and returns the output of the included file. Used for content that was loaded from template files. */
	const CompileModeInclude = 1;
	/** Compiles the Snippet by using the content that was loaded regardless the source or the way it was loaded. Does not evaluate any PHP code inside the contents. */
	const CompileModeNormal = 2;
	/** Compiles the Snippet by evaluating the content that was loaded into. */
	const CompileModeEvaluate = 3;

	/** Snippet isn't yet initialized */
	const CompileStatusNone = 0;
	/** Snippet is initialized */
	const CompileStatusInitialized = 10;
	/** Snippet is setup */
	const CompileStatusSetup = 20;
	/** Snippet is prepared */
	const CompileStatusPrepared = 40;
	/** Snippet is compiled */
	const CompileStatusCompiled = 60;
	/** Snippet's output has been sent to the browser */
	const CompileStatusDisplayed = 100;

	/** Snippet's content source is undefined (usually when no content has been loaded yet) */
	const ContentSourceNone = 'N';
	/** Snippet's content source is a template file */
	const ContentSourceFile = 'F';
	/** Snippet's content source is a static page in the database */
	const ContentSourceStaticPage = 'S';
	/** Snippet's content source is a translation in the database */
	const ContentSourceTranslation = 'T';
	/** Snippet's content source is a dynamic snippet defined in the database */
	const ContentSourceDatabase = 'D';
	/** Snippet's content source is a PHP variable */
	const ContentSourceVariable = 'V';
	#endregion

	#region Events
	const EventOnLoadedContent = 'OnLoadedContent';
	const EventOnParsedContent = 'OnParsedContent';
	const EventOnInitialized   = 'OnInitialized';
	const EventOnSetup         = 'OnSetup';
	const EventOnPreparing     = 'OnPreparing';
	const EventOnPrepared      = 'OnPrepared';
	const EventOnTranslate     = 'OnTranslate';
	const EventOnCompiled      = 'OnCompiled';
	const EventOnDisplaying    = 'OnDisplaying';
	const EventOnDisplayed     = 'OnDisplayed';
	const EventOnRendering     = 'OnRendering';
	const EventOnRendered      = 'OnRendered';
	#endregion

	#region Properties
	#region Basic properties
	/** @var Parameters */
	public Parameters $params;
	/** @var Parameters Parameters that their values will be parsed after snippet's compilation. Used in case parameter values should not be parsed for snippet, plug or property tags. */
	public Parameters $rawParams;
	/** @var ?string A tag or label that is used to name a Snippet */
	public ?string $tag = null;
	/** @var ?string A title for the Snippet */
	public ?string $title = null;
	/** @var string Snippet's charset. Default value is: utf-8 */
	public string $charset = 'utf-8';
	/** @var ?string The tag of the plug this Snippet is assigned to. If value is empty, the Snippet is not assigned to any plug */
	public ?string $plug = null;

	/** @var SnippetCollection Snippet's collection of child Snippets */
	public SnippetCollection $snippets;
	/** @var SlotCollection Snippet's collection of content slots */
	public SlotCollection $slots;

	/** @var int Snippet's compile mode. Valid values are Snippet:CompileMode* constants */
	public int $compileMode = self::CompileModeNormal;
	/** @var int Snippet's compilation status. Valid values are Snippet::CompileStatus* constants */
	public int $compileStatus = self::CompileStatusNone;
	/** @var ?int Snippet's compile order. Used to order the display of snippets when compiling a parent's plug */
	public ?int $compileOrder = null;
	/** @var string Snippet's source type from which it fetched its content. Valid values are Snippet::ContentSource* constants */
	public string $contentSource = self::ContentSourceNone;

	/** @var mixed Snippet's parent object */
	public $parent = null;

	/** @var bool Indicates if the Snippet is standalone or inside a container Snippet's children snippets collection */
	public bool $isStandalone = true;
	#endregion

	#region Protected properties
	/** @var ?string The full path of the file the Snippet got its content from */
	protected ?string $_templateFile = '';

	/** @var Collection Holds all plugs that were found while preparing Snippet's content for compilation */
	protected Collection $plugs;

	/** @var ?string Stores Snippet's content either compiled or not, depending on Snippet's current compilation status */
	protected ?string $_content = '';

	/** @var string Snippet's content type. Default value is: text/html */
	protected string $_contentType = 'text/html';

	protected bool|array $_cache = false;

	/** The Snippet's database tag to use for database-related functions */
	protected ?string $_dbTag = CMS::CMS_DB_TAG;

	/** @var ?array Associative array that stores the Snippet's chain of children compilations; useful for debugging */
	protected ?array $_compilationChain = null;
	#endregion

	#region Static properties
	/** @var ?string The Snippet's database tag to use for database-related functions */
	protected static ?string $_dbTagStatic = CMS::CMS_DB_TAG;

	/**@var string[] Caches content from snippets that were loaded from file templates in order to save disk I/O */
	protected static array $_fileCache = [];

	/** @var int Holds an incremental unique identifier for naming raw parameters globally */
	private static int $_rawParamsCounter = 0;

	/** @var Parameters Stores raw parameters from all Snippets */
	private static Parameters $_rawParams;
	#endregion
	#endregion

	#region Constructor
	/**
	 * @param ?string $tag
	 * @param ?string $content (optional)
	 */
	function __construct() {
		$this->hooks()->register([
									 self::EventOnLoadedContent, self::EventOnParsedContent,
									 self::EventOnInitialized, self::EventOnSetup,
									 self::EventOnPreparing, self::EventOnPrepared,
									 self::EventOnCompiled, self::EventOnTranslate,
									 self::EventOnDisplaying, self::EventOnDisplayed,
									 self::EventOnRendering, self::EventOnRendered
								 ]);

		if (func_num_args() > 0)
			$tag = (string)func_get_arg(0);
		if (func_num_args() > 1)
			$content = (string)func_get_arg(1);

		$this->contentType('text/html');
		$this->charset = 'UTF-8';

		$this->params = new Parameters();
		$this->rawParams = new Parameters();

		$this->snippets = new SnippetCollection();
		$this->snippets->parent($this);

		$this->slots = new SlotCollection();
		$this->plugs = new Collection('string', true);

		if (isset ($tag) && is_string($tag) && strlen($tag) > 0)
			$this->tag = $tag;

		if (isset ($content)) {
			$this->hooks()->disable(self::EventOnLoadedContent);
			$this->loadContentFromVariable($content);
			$this->hooks()->enable(self::EventOnLoadedContent);
		}

		// Set default database tag to CMS
		$this->_dbTag = CMS::CMS_DB_TAG;

		$lang = CMS::translator()->currentLanguage();
		if ($lang) {
			$this->params->__LC = $lang->code;
			$this->params->__CC = $lang->countryCode();
		}
	}

	public static function __init() {
		if (!isset(self::$_rawParams)) {
			self::$_rawParams = new Parameters();
		}
	}
	#endregion

	#region Methods
	#region Load content methods
	public function loadContentFromFile(string $file) {
		if (strpos($file, '/') !== 0)
			$file = '/' . $file;

		// If file path is relative to project, prepend project's filesystem path
		if (strpos($file, CMS::appPath()) !== 0) {
			$file = CMS::appPath() . $file;
		}
		$this->_templateFile = $file;

		if (isset (self::$_fileCache[$file])) {
			$this->_content = self::$_fileCache[$file];
		}
		else {
			if (!file_exists($file)) {
				CMS::logger()->warning("Snippet file '$file' was not found.");
				$this->_content = '';
			}
			else {
				$this->_content = self::$_fileCache[$file] = file_get_contents($file);
			}
		}


		$this->compileStatus = ($this->compileStatus > self::CompileStatusNone) ? self::CompileStatusInitialized : self::CompileStatusNone;
		$this->compileMode = self::CompileModeInclude;
		$this->contentSource = self::ContentSourceFile;

		// Parse & create any slots found in the content
		$this->parseSlots();
		$this->parseHooks();

		$this->onLoadedContent();

		$args = new EventArgs($this);
		$args->file = $file;    // Undocumented property file
		$this->trigger(self::EventOnLoadedContent, $args);

		return $this->_content;
	}

	public function loadContentFromStaticPage(string $tag) {
		$lang = CMS::translator()->currentLanguage()->code;

		$db = CMS::db();
		$tag = trim($tag);
		$sql = 'SELECT T1.webpage_id, T2.title, T2.content, T1.photo_url, T2.meta_title, T2.meta_description, T2.meta_keywords
				FROM cms_webpages T1
				LEFT JOIN cms_webpagesTr T2 ON T1.webpage_id=T2.webpage_id AND T2.language_code=:lang
				WHERE tag=:tag';
		$row = $db->fetch($sql, array (':lang' => $lang, ':tag' => $tag));
		if (!$row) return false;

		$row['tags'] = false;
		$sql = "SELECT T2.tag FROM cms_webpages_tags T1 LEFT JOIN cms_webpages_tagsTr T2 ON T2.tag_id=T1.tag_id AND T2.language_code=:lang WHERE T1.webpage_id=:webpage_id";
		$tags = $db->fetchAll($sql, array (':lang' => $lang, ':webpage_id' => $row['webpage_id']));
		if ($tags)
			foreach ($tags as $tag)
				$row['tags'][] = $tag['tag'];

		$this->_templateFile = null;
		$this->compileStatus = ($this->compileStatus > self::CompileStatusNone) ? self::CompileStatusInitialized : self::CompileStatusNone;
		$this->contentSource = self::ContentSourceStaticPage;
		$this->compileMode = self::CompileModeNormal;

		$this->_content = $row['content'];

		// Add static page information as parameters
		$this->id = (int)$row['webpage_id'];
		$this->title = $this->params->staticPageTitle = $row['title'];
		$this->params->staticPageMetaTitle = $row['meta_title'];
		$this->params->staticPageMetaDescription = $row['meta_description'];
		$this->params->staticPageMetaKeywords = $row['meta_keywords'];
		$this->params->staticPageTagsCloud = $row['tags'];
		$this->params->staticPagePhotoUrl = $row['photo_url'];

		// Parse & create any slots found in the content
		$this->parseSlots();
		$this->parseHooks();

		$this->onLoadedContent();

		$args = new EventArgs ($this);
		$args->tag = $tag;
		$this->trigger(self::EventOnLoadedContent, $args);

		return $this->_content;
	}

	public function loadContentFromTranslation(string $tag, $section) {
		$lang = CMS::translator()->currentLanguage()->code;

		$db = CMS::db();
		$tag = trim($tag);
		$section = trim($section);
		$sql = 'SELECT value FROM cms_translationsTr T1 JOIN cms_translations_sections T2 ON T1.section_id=T2.section_id WHERE T2.tag=:section AND T1.language_code=:lang AND T1.tag=:tag';
		$content = $db->fetchColumn($sql, "value", array (':section' => $section, ':lang' => $lang, ':tag' => $tag));

		$this->_templateFile = null;
		$this->compileStatus = ($this->compileStatus > self::CompileStatusNone) ? self::CompileStatusInitialized : self::CompileStatusNone;
		$this->contentSource = self::ContentSourceTranslation;
		$this->compileMode = self::CompileModeNormal;

		$this->_content = $content;

		// Parse & create any slots found in the content
		$this->parseSlots();
		$this->parseHooks();

		$this->onLoadedContent();

		$args = new EventArgs($this);
		$args->tag = $tag;
		$this->trigger(self::EventOnLoadedContent, $args);

		return $this->_content;
	}

	public function loadContentFromDb(string $tag, bool $evaluate = false) {
		$this->_templateFile = null;
		$this->compileStatus = ($this->compileStatus > self::CompileStatusNone) ? self::CompileStatusInitialized : self::CompileStatusNone;

		$db = CMS::db();
		$schema = $db->getSchemaName();
		$tag = trim($tag);
		$lang = CMS::translator()->currentLanguage()->code;
		$sql = "SELECT T1.title, T2.content
				FROM $schema.cms_snippets T1
				LEFT JOIN $schema.cms_snippetsTr T2 ON T2.snippet_id=T1.snippet_id AND T2.language_code=:lang
				WHERE T1.tag=:tag";
		$row = $db->fetch($sql, array (':lang' => $lang, ':tag' => $tag));
		if ($row === false) {
			return false;
		}

		$content = $row['content'] ?? '';
		$this->title = $row['title'] ?? '';

		if ($evaluate)
			$this->compileMode = self::CompileModeEvaluate;
		else
			$this->compileMode = self::CompileModeNormal;

		$this->_content = $content;
		$this->contentSource = self::ContentSourceDatabase;

		// Parse & create any slots found in the content
		$this->parseSlots();
		$this->parseHooks();

		$this->onLoadedContent();

		$args = new EventArgs($this);
		$args->tag = $tag;
		$this->trigger(self::EventOnLoadedContent, $args);

		return $this->_content;
	}

	public function loadContentFromVariable(string $content): ?string {
		$this->_templateFile = null;
		$this->compileStatus = ($this->compileStatus > self::CompileStatusNone) ? self::CompileStatusInitialized : self::CompileStatusNone;
		$this->compileMode = self::CompileModeNormal;
		$this->contentSource = self::ContentSourceVariable;

		$this->_content = $content;

		// Parse & create any slots found in the content
		$this->parseSlots();
		$this->parseHooks();

		$this->onLoadedContent();

		$this->trigger(self::EventOnLoadedContent, new EventArgs ($this));

		return $this->_content;
	}
	#endregion

	#region Placeholder processing methods
	protected function convertPageElements(): Snippet {
		foreach ($this->snippets as $s) {
			if (class_exists('\\aneya\\UI\\Page\\PageElement') && $s instanceof \aneya\UI\Page\PageElement) {
				$idx = $this->snippets->indexOf($s);

				$s2 = new Snippet($s->renderTag());
				$s2->renderOrder($s->renderOrder());
				$s2->loadContentFromVariable($s->render());

				$this->snippets->replaceAt($s2, $idx);
			}
		}

		return $this;
	}

	protected function maskRawParam($param, $value): Snippet {
		$cnt = self::$_rawParamsCounter++;
		$key = "__raw_param_$cnt";
		self::$_rawParams->add($key, $value);

		// Re-write parameter
		$this->_content = str_ireplace('{%' . $param . '}', '{%' . $key . '}', $this->_content);

		return $this;
	}


	protected function parseForms(): Snippet {
		preg_match_all('/\{form\(([^)]+)\)\}/i', $this->_content, $matches, PREG_SET_ORDER);
		if (count($matches) > 0) {
			foreach ($matches as $m) {
				$htmlId = $m[1] . '_' . substr(md5(rand()), 0, 5);
				$div = "<div id=\"$htmlId\"></div>\n";
				$s = \aneya\Forms\Form::getFormLoaderSnippet($htmlId, $m[1]);
				$value = $div . $s->compile();
				$this->replaceForm($m[1], $value);
			}
		}

		return $this;
	}

	protected function parseHooks(): Snippet {
		preg_match_all('/\{event\(([^)]+)\)\}/i', $this->_content, $matches, PREG_SET_ORDER);
		if (count($matches) > 0) {
			foreach ($matches as $m) {
				$h = ($this->hooks()->get($m[1]));
				// If event is already registered (usually due to a listener definition) mark it as an inline hook)
				if ($h instanceof Hook && $h->tag === null)
					$h->tag = '__inline_hooks';
				// Otherwise, register the new event under inline hooks
				else
					$this->hooks()->register($m[1], '__inline_hooks');
			}

			$this->trigger('onHooksParsed', new EventArgs ($this));
		}

		return $this;
	}

	protected function parseProperties(): Snippet {
		preg_match_all('/\{\$([\w]+)\}/i', $this->_content, $matches, PREG_SET_ORDER);
		if (count($matches) > 0) {
			foreach ($matches as $m) {
				$prop = $m[1];
				if ((isset($this->$prop) && is_scalar($this->$prop)) || $this->$prop == '') {
					$value = (string)$this->$prop;

					if ($this->contentIsJavascript())
						$value = JavascriptUtils::escape($value, '\'');

					$this->_content = str_ireplace('{$' . $prop . '}', $value, $this->_content);
				}
			}
		}

		return $this;
	}

	protected function parseSlots(): Snippet {
		preg_match_all('/\{slot\(([^)]+)\)\}/i', $this->_content, $matches, PREG_SET_ORDER);
		if (count($matches) > 0) {
			foreach ($matches as $m) {
				$tag = trim($m[1]);
				if (!$this->slots->getByTag($tag)) {
					$this->slots->add($slot = new Slot($tag));
					$slot->parent($this);
				}
			}
		}
		preg_match_all('/\{plug\(([^)]+)\)\}/i', $this->_content, $matches, PREG_SET_ORDER);	// Compatibility with previous versions
		if (count($matches) > 0) {
			foreach ($matches as $m) {
				$tag = trim($m[1]);
				if (!$this->slots->getByTag($tag)) {
					$this->slots->add($slot = new Slot($tag));
					$slot->parent($this);
				}
			}
		}

		return $this;
	}

	protected function parseSnippets(): Snippet {
		preg_match_all('/\{snippet\(([^)]+)\)\}/i', $this->_content, $matches, PREG_SET_ORDER);
		if (count($matches) > 0) {
			foreach ($matches as $m) {
				$s = new Snippet ();
				$s->loadContentFromDb($m[1]);
				$value = $s->compile();
				$this->replaceSnippet($m[1], $value);
			}
		}

		return $this;
	}


	protected function replaceForm($tag, $value): Snippet {
		$this->_content = str_ireplace("{form(" . $tag . ")}", $value, $this->_content);

		return $this;
	}

	protected function replaceHook($tag, $value): Snippet {
		$this->_content = str_ireplace("{event(" . $tag . ")}", $value, $this->_content);

		return $this;
	}

	protected function replaceParam($param, $value): Snippet {
		// Replace parameter only if its value is scalar
		if (is_null($value) || is_scalar($value)) {
			if (!isset ($value) || $value === null)
				$value = '';
		}
		else {
			if (is_object($value) && method_exists($value, '__toString'))
				$value = (string)$value;
			else
				$value = '';
		}

		if ($this->contentIsJavascript())
			$value = JavascriptUtils::escape($value, '\'');

		$this->_content = str_ireplace('{%' . $param . '}', $value, $this->_content);

		return $this;
	}

	protected function replaceSlot($slot, $value): Snippet {
		if ($this->contentIsJavascript())
			$value = JavascriptUtils::escape($value, '\'');

		$this->_content = str_ireplace("{slot(" . $slot . ")}", $value, $this->_content);
		$this->_content = str_ireplace("{plug(" . $slot . ")}", $value, $this->_content);		// Compatibility with previous versions

		return $this;
	}

	protected function replaceSnippet($tag, $value): Snippet {
		if ($this->contentIsJavascript())
			$value = JavascriptUtils::escape($value, '\'');

		$this->_content = str_ireplace("{snippet(" . $tag . ")}", $value, $this->_content);

		return $this;
	}

	protected function replaceTranslations(): Snippet {
		$tr = CMS::translator();

		// Replace all translated texts within the template
		preg_match_all('/\{Tr\(([^\,]+),([^\}]+)\)\}/i', $this->_content, $matches, PREG_SET_ORDER);
		if (count($matches) > 0) {
			if ($this->contentIsJavascript()) {
				foreach ($matches as $m) {
					@list($tag, $defaultTranslation) = explode(',', $m[2], 2);
					$defaultTranslation = $defaultTranslation ?? $tag;
					$value = $tr->translate($tag, $m[1], trim($defaultTranslation));

					// Escape new lines & single quotes
					$value = str_replace(["\r", "\n"], ' ', $value);
					$value = JavascriptUtils::escape($value, '\'');

					$this->_content = str_ireplace($m[0], $value, $this->_content);
				}
			}
			else {
				foreach ($matches as $m) {
					@list($tag, $defaultTranslation) = explode(',', $m[2], 2);
					$defaultTranslation = $defaultTranslation ?? $tag;
					$this->_content = str_ireplace($m[0], $tr->translate($tag, $m[1], $defaultTranslation), $this->_content);
				}
			}
			$this->_content = preg_replace('/\{Tr\(([^\,]+),([^\}]+)\)\}/i', "$2", $this->_content);
		}

		return $this;
	}


	protected function clearHooks(): Snippet {
		$this->_content = preg_replace('/\{event\(([^)]+)\)\}/i', '', $this->_content);

		return $this;
	}

	protected function clearSlots(): Snippet {
		$this->_content = preg_replace('/\{slot\(([^)]+)\)\}/i', '', $this->_content);
		$this->_content = preg_replace('/\{plug\(([^)]+)\)\}/i', '', $this->_content);			// Compatibility with previous versions

		return $this;
	}

	protected function clearSnippets(): Snippet {
		$this->_content = preg_replace('/\{snippet\(([^)]+)\)\}/i', '', $this->_content);

		return $this;
	}
	#endregion

	#region Event methods
	protected function onInitialize() { }

	protected function onInitialized() { }

	protected function onLoadedContent() { }

	protected function onSetup() {
		// Setup children snippets
		foreach ($this->slots->all() as $slot)
			$slot->snippets->setup();

		$this->snippets->setup();
	}

	protected function onPreparing() { }

	protected function onPrepare() {
		if ($this->compileMode == self::CompileModeInclude) {
			ob_start();
			try {
				include $this->_templateFile;
			}
			catch (\Exception $e) {
				CMS::app()->log(new ApplicationError("Error loading snippet file '$this->_templateFile'. Details:\n" . $e->getMessage(), $e->getCode(), $e));
			}
			$this->_content = ob_get_contents();
			ob_end_clean();
		}

		// Convert page elements into snippets so that their inner snippet & plug tags can be parsed and used in the compilation chain
		if (CMS::modules()->isAvailable('aneya/ui')) {
			$this->convertPageElements();
		}

		#region Trigger all event hooks (if any)
		$events = $this->hooks()->getByTag('__inline_hooks');
		if (count($events) > 0) {
			foreach ($events as $e) {
				$output = '';
				$triggers = $this->trigger($e->name, new SnippetCompileEventArgs($this, $this->_content));
				foreach ($triggers as $trigger) {
					if (!($trigger instanceof SnippetCompileEventStatus))
						continue;

					$output .= ($trigger->content instanceof IRenderable) ? $trigger->content->render() : $trigger->content;
				}
				$this->replaceHook($e->name, $output);
			}
		}
		#endregion

		// Clear uncaught event hook tags (if any)
		$this->clearHooks();

		#region Prepare any children snippets and place them into the corresponding plugs
		$this->parseSlots();
		foreach ($this->slots->all() as $slot) {
			$content = $slot->render();
			$this->replaceSlot($slot->tag, $content);
		}
		#endregion

		#region Prepare any root-level child snippets and place them into the template
		foreach ($this->snippets->sort()->all() as $snippet) {
			if ($snippet instanceof Snippet) {
				$content = $snippet->prepare();
				$this->replaceSnippet($snippet->tag, $content);
			}
			else {
				$content = $snippet->render();
				$this->replaceSnippet($snippet->renderTag(), $content);
			}
		}
		#endregion

		// Try to prepare any unmatched child snippets by their tag in the snippets database
		$this->parseSnippets();

		// Clear uncaught slot tags (if any)
		$this->clearSlots();

		// Clear uncaught snippet tags (if any)
		$this->clearSnippets();

		if (CMS::modules()->isAvailable('aneya/forms')) {
			$this->parseForms();
		}

		// Replace parameters with their values
		foreach ($this->params->all() as $kv) {
			if ($kv->value == null) // Convert null to scalar
				$kv->value = '';

			$this->replaceParam($kv->key, $kv->value);
		}

		// Replace property placeholders with object's corresponding property values
		$this->parseProperties();
	}

	protected function onPrepared() { }

	protected function onCompile() {
		// Replace all translated texts
		$this->translate();

		// Replace again parameters on placeholders found in translated texts
		if ($this->params->count() > 0) {
			foreach ($this->params->all() as $kv) {
				if ($kv->value == null) $kv->value = "";

				$this->replaceParam($kv->key, $kv->value);
			}
		}

		// Replace again any properties found in translated texts
		$this->parseProperties();


		// If this snippet is top-most (not contained in any other snippet)
		if ($this->isStandalone) {
			// We can now replace raw parameters for all snippets in the chain, starting from the global params, then to the local params
			if (self::$_rawParams->count() > 0) {
				foreach (self::$_rawParams->all() as $kv) {
					if ($kv->value == null) {
						$kv->value = '';
					}

					$this->replaceParam($kv->key, $kv->value);
				}
			}
			if ($this->rawParams->count() > 0) {
				foreach ($this->rawParams->all() as $kv) {
					if ($kv->value == null) {
						$kv->value = '';
					}

					$this->replaceParam($kv->key, $kv->value);
				}
			}
		}
		else {
			// Snippet is contained in another snippet,
			// so pass its raw params to the global params container
			foreach ($this->rawParams as $kv) {
				$this->maskRawParam($kv->key, $kv->value);
			}
		}

		// If snippet is cacheable, update its cache
		if ($this->isCacheable()) {
			Cache::store($this->_content, null, $this->_cache['TAG'], $this->_cache['CATEGORY']);
		}
	}

	protected function onCompiled() { }

	protected function onTranslate() {
		$this->replaceTranslations();
	}

	protected function onDisplaying() { }

	protected function onDisplay() {
		header("Content-Type: " . $this->contentType() . "; charset=" . $this->charset);
		echo $this->_content;
	}

	protected function onDisplayed() { }
	#endregion

	#region Core actions
	/**
	 * Performs any initializations on the Snippet
	 *
	 * @param bool $force Forces initialization even if snippet is already initialized
	 *
	 * @triggers OnInitialized
	 */
	public final function initialize(bool $force = true) {
		if ($this->compileStatus >= self::CompileStatusInitialized && !$force)
			return;

		$this->onInitialize();

		#region Also initialize any child snippets
		foreach ($this->snippets->all() as $s) {
			if ($s instanceof Snippet) {
				$s->initialize($force);
			}
		}
		#endregion

		$this->compileStatus = self::CompileStatusInitialized;
		$this->onInitialized();
		$this->trigger(self::EventOnInitialized, new EventArgs($this));
	}

	/** Performs any setups on the Snippet before starting the preparation to compile. */
	public final function setup(): static {
		if ($this->compileStatus < self::CompileStatusInitialized)
			$this->initialize();

		if ($this->compileStatus >= self::CompileStatusSetup)
			return $this;

		// Pass regional/language settings, if available
		$lang = CMS::translator()->currentLanguage();
		if ($lang) {
			$this->params->__LC = $lang->code;
			$this->params->__CC = $lang->countryCode();
		}

		// Call own setup, then call external setup listeners
		$this->onSetup();
		$this->trigger(self::EventOnSetup, new EventArgs($this));

		$this->compileStatus = self::CompileStatusSetup;

		return $this;
	}

	/**
	 * Prepares the Snippet for compilation. During preparation, the Snippet prepares any sub-elements found in placeholders, replaces parameters with their values
	 * and unifies the total content of the Snippet and its sub-elements into one content.
	 *
	 * @return string The prepared content
	 * @triggers OnPreparing, OnPrepare, OnPrepared
	 */
	public final function prepare(): ?string {
		if ($this->compileStatus < self::CompileStatusSetup)
			$this->setup();

		if ($this->compileStatus >= self::CompileStatusPrepared)
			return $this->_content;

		$args = new SnippetCompileEventArgs($this);

		$this->onPreparing();
		$this->trigger(self::EventOnPreparing, $args);
		$this->onPrepare();
		$this->compileStatus = self::CompileStatusPrepared;
		$args->content = $this->_content;
		$this->onPrepared();
		$this->trigger(self::EventOnPrepared, $args);

		return $this->_content;
	}

	/**
	 * Compiles (if standalone) or prepares content and returns the output.
	 *
	 * @return string
	 */
	public function render(): string {
		if ($this->isStandalone)
			return $this->compile() ?? '';
		else
			return $this->prepare() ?? '';
	}

	/**
	 * Compiles the Snippet's content and returns the final output, performing any translations found.
	 * If Snippet isn't yet prepared, the function automatically calls Snippet's prepare() method to prepare the content for compilation.
	 * If Snippet is flagged as cacheable, then if cache is not outdated, it directly returns the cached content; otherwise it compiles content and updates the cache.
	 *
	 * @return string Snippet's final output
	 * @triggers OnCompiled
	 */
	public final function compile(): ?string {
		#region Fetch from cache if snippet is cacheable and is not outdated
		// Check first if snippet is cacheable (has cache information)
		if ($this->isCacheable()) {
			$content = Cache::retrieve($this->_cache['CATEGORY'], $this->_cache['TAG']);

			// If cache is the latest, just return the cached content
			if ($content) {
				$this->trigger(self::EventOnCompiled, new SnippetCompileEventArgs ($this, $content));
				return $content;
			}
		}
		#endregion

		if ($this->compileStatus < self::CompileStatusPrepared)
			$this->prepare();

		if ($this->compileStatus >= self::CompileStatusCompiled)
			return $this->_content;

		$this->onCompile();
		$this->compileStatus = self::CompileStatusCompiled;
		$this->onCompiled();
		$this->trigger(self::EventOnCompiled, new SnippetCompileEventArgs($this, $this->_content));

		return $this->_content;
	}

	/**
	 * Sends Snippet's content to the standard output.
	 * If Snippet is not yet compiled, the function automatically calls Snippet's compile() method to compile the content for output.
	 *
	 * @return string
	 * @triggers OnDisplaying, OnDisplayed
	 */
	public final function display(): ?string {
		// If display() method is called, then it's definitely a standalone snippet
		$this->isStandalone = true;

		if ($this->compileStatus < self::CompileStatusCompiled)
			$this->compile();

		$args = new SnippetCompileEventArgs($this, $this->_content);

		$this->onDisplaying();
		$this->trigger(self::EventOnDisplaying, $args);
		$this->onDisplay();
		$this->compileStatus = self::CompileStatusDisplayed;
		$this->onDisplayed();
		$this->trigger(self::EventOnDisplayed, $args);

		return $this->_content;
	}

	/**
	 * Replaces any translatable content found inside the Snippet's content with their corresponding translated values in the current language
	 */
	public final function translate() {
		$statuses = $this->trigger(self::EventOnTranslate, new SnippetCompileEventArgs ($this, $this->_content));
		foreach ($statuses as $status) {
			if ($status->isHandled && $status instanceof SnippetCompileEventStatus) {
				$this->_content = $status->content instanceof IRenderable ? $status->content->render() : $status->content;
				return;
			}
		}

		$this->onTranslate();
	}
	#endregion

	#region Get/set methods
	/** @inheritdoc */
	public function contentType(string $contentType = null): string {
		if (isset($contentType))
			$this->_contentType = $contentType;

		return $this->_contentType;
	}

	/** Returns true if snippet's content compilation will result in Javascript code */
	public function contentIsJavascript(): bool {
		return $this->_contentType == 'text/javascript' || $this->_contentType == 'application/javascript';
	}

	/** @inheritdoc */
	public function renderOrder(int $order = null): int {
		if (is_numeric($order)) {
			$this->compileOrder = $order;
		}

		return $this->compileOrder;
	}

	public function renderTag(string $tag = null): string {
		if (is_string($tag)) {
			$this->tag = $tag;
		}

		return $this->tag;
	}

	/** @deprecated Returns the database tag the snippet uses on database calls */
	public function getDatabaseTag(): ?string {
		return $this->_dbTag;
	}

	/** @deprecated Sets the database tag to be used by the snippet for database calls */
	public function setDatabaseTag($tag) {
		$this->_dbTag = $tag;
	}

	/** Returns the Snippet's chain of children compilations */
	public function getCompilationChain() {
		if (count($this->_compilationChain) == 1 && count($this->_compilationChain['.']) == 0)
			return $this->tag;

		return array ($this->tag => $this->_compilationChain);
	}

	/** @deprecated Returns the database tag the snippet uses on database calls */
	public static function getDatabaseTagSt(): string {
		return static::$_dbTagStatic;
	}

	/** @deprecated Sets the database tag to be used by the snippet for database calls */
	public static function setDatabaseTagSt($tag) {
		static::$_dbTagStatic = $tag;
	}
	#endregion

	#region Caching methods
	public function getCacheInfo() {
		return $this->_cache;
	}

	public function setCacheInfo($category, $tag) {
		$this->_cache = array ("CATEGORY" => $category, "TAG" => $tag);
	}

	public function isCacheable(): bool {
		return is_array($this->_cache);
	}
	#endregion
	#endregion

	#region Static methods
	/** Returns all snippets translated in current language. */
	public static function allSnippets(string $prefix = ''): array {
		$lang = CMS::translator()->currentLanguage();
		$sql = 'SELECT T.tag, Tr.content
				FROM cms_snippets T
				JOIN cms_snippetsTr Tr ON Tr.snippet_id=T.snippet_id AND Tr.language_code=:lang
				WHERE T.tag LIKE :prefix';
		$rows = CMS::db()->fetchAll($sql, [':lang' => $lang->code, ':prefix' => $prefix . '%']);

		$trans = [];
		if ($rows)
			foreach ($rows as $row) {
				$tag = preg_replace('/[^\da-z]/i', '_', strtolower($row['tag']));
				$trans[$tag] = $row['content'];
			}

		return $trans;
	}

	/** Instantiates & returns a new Snippet with content loaded from the given file */
	public static function fromFile(string $filename): Snippet {
		$s = new Snippet();
		$s->loadContentFromFile($filename);
		return $s;
	}

	/** Instantiates & returns a new Snippet with content loaded from the given static page */
	public static function fromStaticPage(string $tag): Snippet {
		$s = new Snippet();
		$s->loadContentFromStaticPage($tag);
		return $s;
	}

	/** Instantiates & returns a new Snippet with content loaded from the given database snippets entry */
	public static function fromDb(string $tag, bool $evaluate = false): Snippet {
		$s = new Snippet();
		$s->loadContentFromDb($tag, $evaluate);
		return $s;
	}

	/** Instantiates & returns a new snippet with content loaded from the given string variable */
	public static function fromVariable(string $content): Snippet {
		$s = new Snippet();
		$s->loadContentFromVariable($content);
		return $s;
	}
	#endregion
}

Snippet::__init();
