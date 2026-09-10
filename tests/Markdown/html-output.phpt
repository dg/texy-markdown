<?php declare(strict_types=1);

/**
 * Test: the HTML path and what the configuration of Markdown adds on top.
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


test('its own configuration gives typography and link roots', function () {
	$markdown = new Markdown;
	$markdown->htmlOutput->linkBaseUrl = 'https://example.com/';

	$html = $markdown->toHtml('see [there](page.html) for "quotes"');
	Assert::contains('href="https://example.com/page.html"', $html);
	Assert::contains("\u{201C}quotes\u{201D}", $html);
});


test('typography can be switched off, hyphenation is off by default', function () {
	$markdown = new Markdown;
	$word = str_repeat('slabika', 10);
	Assert::same("<p>$word</p>", trim($markdown->toHtml($word)));

	$markdown->config->typography = false;
	Assert::same('<p>"quotes"</p>', trim($markdown->toHtml('"quotes"')));
});


test('the defaults apply only to a configuration of its own', function () {
	$config = new Texy\Config;
	$markdown = new Markdown($config);
	Assert::same('cs', $markdown->config->locale);
	Assert::true($markdown->config->hyphenation);
});


test('objects of Texy can be shared, and share their changes', function () {
	$texy = new Texy\Texy;
	$markdown = new Markdown(config: $texy->config, htmlOutput: $texy->htmlOutput, htmlPolicy: $texy->htmlPolicy);
	Assert::same($texy->urlPolicy, $markdown->urlPolicy);

	$texy->htmlOutput->linkBaseUrl = 'https://example.com/';
	Assert::contains('href="https://example.com/page.html"', $markdown->toHtml('[there](page.html)'));
});


test('a pair of policies', function () {
	$urlPolicy = new Texy\UrlPolicy;
	$htmlPolicy = new Texy\HtmlPolicy($urlPolicy);

	$markdown = new Markdown;
	Assert::same($markdown->urlPolicy, $markdown->htmlPolicy->urlPolicy);

	$markdown = new Markdown(htmlPolicy: $htmlPolicy);
	Assert::same($urlPolicy, $markdown->urlPolicy);

	$markdown = new Markdown(urlPolicy: $urlPolicy);
	Assert::same($urlPolicy, $markdown->htmlPolicy->urlPolicy);

	$markdown = new Markdown(htmlPolicy: $htmlPolicy, urlPolicy: $urlPolicy);
	Assert::same($htmlPolicy, $markdown->htmlPolicy);

	Assert::exception(
		fn() => new Markdown(htmlPolicy: $htmlPolicy, urlPolicy: new Texy\UrlPolicy),
		Texy\InvalidArgumentException::class,
		'The UrlPolicy must be the one the HtmlPolicy uses.',
	);
});


test('an instance cannot be cloned', function () {
	Assert::exception(fn() => clone new Markdown, LogicException::class, 'Clone is not supported.');
});


test('the level of a heading is written, and shifted by the configuration', function () {
	$markdown = new Markdown;
	$markdown->htmlOutput->indent = false;
	Assert::match('<h1>a</h1>%A?%<h2>b</h2>', $markdown->toHtml("# a\n\n## b\n"));
	Assert::match('<h2>a</h2>', trim($markdown->toHtml('## a')));

	$markdown->config->headingTop = 2;
	Assert::match('<h2>a</h2>%A?%<h3>b</h3>', $markdown->toHtml("# a\n\n## b\n"));
});


test('heading identifiers are generated on request', function () {
	$markdown = new Markdown;
	$markdown->config->headingIds = true;
	Assert::match('<h1 id="toc-a-b">a b</h1>', trim($markdown->toHtml('# a b')));
});


test('safe mode leaves no raw HTML and no dangerous URL', function () {
	$markdown = new Markdown;
	Configurator::safeMode($markdown);

	$html = $markdown->toHtml("<script>alert(1)</script>\n\n[x](javascript:alert(1))\n");
	Assert::notContains('<script', $html);
	Assert::contains('&lt;script&gt;', $html);
	Assert::notContains('javascript:', $html);
});


test('safe mode keeps images from http(s), any other becomes its alternative text', function () {
	$markdown = new Markdown;
	Configurator::safeMode($markdown);

	Assert::match(
		'<p>a <img src="http://x.cz/a.jpg" alt="x"> b c d</p>',
		$markdown->toHtml('a ![x](http://x.cz/a.jpg) ![b](javascript:alert(1)) ![c](data:image/png;base64,x) d'),
	);
});


test('an image rejected by the URL policy renders as its alternative text', function () {
	$markdown = new Markdown;
	$markdown->urlPolicy->imagePattern = '~https?:~A';

	Assert::match(
		'<p>a x b</p>',
		$markdown->toHtml('a ![x](javascript:alert(1)) b'),
	);
});


test('creating a renderer does not change the configuration', function () {
	$markdown = new Markdown;
	$before = $markdown->htmlOutput->getRenderers();

	$markdown->createHtmlRenderer();
	$markdown->createHtmlRenderer();

	Assert::same($before, $markdown->htmlOutput->getRenderers(), 'handlers must not pile up on the config');
});
