<?php declare(strict_types=1);

/**
 * Test: the HTML path - the four node types Texy's renderer has to be taught, and
 * what a configured Texy adds on top.
 */

use Tester\Assert;
use Texy\Markdown\Configurator;
use Texy\Markdown\Markdown;

require __DIR__ . '/../bootstrap.php';


test('a tight list renders without paragraph wrappers, a loose one with them', function () {
	$markdown = new Markdown;

	Assert::notContains('<p>', $markdown->toHtml("- a\n- b\n"));
	Assert::contains('<p>a</p>', $markdown->toHtml("- a\n\n- b\n"));
});


test('raw HTML and soft breaks survive the renderer', function () {
	$markdown = new Markdown;

	Assert::contains('<b>bold</b>', $markdown->toHtml('a <b>bold</b> word'));
	// the block goes through well-forming like any other markup, so its own line
	// breaks are reformatted - except where the content model preserves them
	Assert::contains('<div>block</div>', $markdown->toHtml("<div>\nblock\n</div>\n"));
	Assert::contains("<pre>\n  a\n   b\n</pre>", $markdown->toHtml("<pre>\n  a\n   b\n</pre>\n"));
	// a soft break is whitespace, and Texy's formatter decides which whitespace
	Assert::contains('first second', $markdown->toHtml("first\nsecond\n"));
	Assert::contains('<br>', $markdown->toHtml("first  \nsecond\n"));
});


test('a configured Texy contributes typography and its own roots', function () {
	$markdown = new Markdown;
	$texy = new Texy\Texy;
	$texy->htmlOutput->linkRoot = 'https://example.com/';

	$html = $markdown->toHtml('see [there](page.html) for "quotes"', $texy);
	Assert::contains('href="https://example.com/page.html"', $html);
	Assert::notContains('"quotes"', $html, 'straight quotes should have been typographed');
});


test('safe mode leaves no raw HTML and no dangerous URL', function () {
	$markdown = new Markdown;
	$texy = new Texy\Texy;
	Configurator::safeMode($markdown, $texy);

	$html = $markdown->toHtml("<script>alert(1)</script>\n\n[x](javascript:alert(1))\n", $texy);
	Assert::notContains('<script', $html);
	Assert::contains('&lt;script&gt;', $html);
	Assert::notContains('javascript:', $html);
});


test('safe mode disables images', function () {
	$markdown = new Markdown;
	$texy = new Texy\Texy;
	Configurator::safeMode($markdown, $texy);

	Assert::match(
		'<p>a !<a href="http://x.cz/a.jpg" rel="nofollow">x</a> b</p>',
		$markdown->toHtml('a ![x](http://x.cz/a.jpg) b', $texy),
	);
});


test('an image rejected by the URL policy renders as its alternative text', function () {
	$markdown = new Markdown;
	$texy = new Texy\Texy;
	$texy->urlPolicy->imagePattern = '~https?:~A';

	Assert::match(
		'<p>a x b</p>',
		$markdown->toHtml('a ![x](javascript:alert(1)) b', $texy),
	);
});


test('the given Texy is not modified', function () {
	$markdown = new Markdown;
	$texy = new Texy\Texy;
	$before = $texy->htmlOutput->getRenderers();

	$markdown->createHtmlRenderer($texy);
	$markdown->createHtmlRenderer($texy);

	Assert::same($before, $texy->htmlOutput->getRenderers(), 'handlers must not pile up on the config');
});
