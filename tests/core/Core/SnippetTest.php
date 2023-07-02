<?php
require_once (__DIR__ . '/../../../aneya.php');

use aneya\Snippets\Snippet;
use PHPUnit\Framework\TestCase;

class SnippetTest extends TestCase {
	public function testSnippet() {
		$container = new Snippet ('container');
		$container->loadContentFromVariable("<h1>test</h1>\n{plug(content)}\n{snippet(sn)}\n{param1}");
		$container->params->param1 = 'TheEnd';

		$s = new Snippet('s1');
		$s->loadContentFromVariable("<h2>s1</h2>\n");
		$container->slots->getByTag('content')->snippets->add($s);

		$s = new Snippet('s2');
		$s->compileOrder = -1;
		$s->loadContentFromVariable("<h2>s2</h2>\n");
		$container->slots->getByTag('content')->snippets->add($s);

		$s = new Snippet('sn');
		$s->loadContentFromVariable($s->tag . ' is standalone');
		$container->snippets->add($s);

		$output = $container->compile ();
		$this->assertEquals("<h1>test</h1>\n<h2>s2</h2>\n<h2>s1</h2>\n\nsn is standalone\nTheEnd", $output);
	}
}
