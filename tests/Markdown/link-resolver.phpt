<?php declare(strict_types=1);

/**
 * Test: references the document does not define go to the link resolver of the configuration.
 */

use Tester\Assert;
use Texy\Markdown\Configurator;
use Texy\Markdown\Markdown;
use Texy\Nodes;

require __DIR__ . '/../bootstrap.php';


function createMarkdown(Closure $resolver): Markdown
{
	$markdown = new Markdown;
	$markdown->config->linkResolver = $resolver;
	return $markdown;
}


/** @return list<array{?string, string, bool}>  url, text and isBareRef of every link */
function describeLinks(Markdown $markdown, string $source): array
{
	return array_map(
		fn(Nodes\LinkNode $link) => [$link->url, $link->content->getPlainText(), $link->isBareRef],
		$markdown->parse($source)->find(Nodes\LinkNode::class),
	);
}


test('shortcut, collapsed and full references', function () {
	$names = [];
	$markdown = createMarkdown(function (string $name) use (&$names) {
		$names[] = $name;
		return '/wiki/' . $name;
	});

	Assert::same(
		[['/wiki/Page', 'Page', true], ['/wiki/Page', 'Page', true], ['/wiki/Other  Page', 'text', false]],
		describeLinks($markdown, '[Page] [Page][] [text][Other  Page]'),
	);
	Assert::same(['Page', 'Page', 'Other  Page'], $names); // as written, not case-folded or collapsed
});


test('a resolver answering null leaves the brackets as text', function () {
	$markdown = createMarkdown(fn() => null);

	Assert::same("<p>[Page] [Page][] [text][page]</p>\n", $markdown->toHtml('[Page] [Page][] [text][page]'));
});


test('a definition of the document wins and its URL keeps %s', function () {
	$markdown = createMarkdown(fn() => Assert::fail('The resolver must not be asked.'));

	Assert::same(
		[['/def/%s', 'Page', true], ['/def/%s', 'Page', true], ['/def/%s', 'text', false]],
		describeLinks($markdown, "[Page] [Page][] [text][page]\n\n[page]: /def/%s"),
	);
});


test('%s in the URL of the resolver stands for the text, except in a bare reference', function () {
	$markdown = createMarkdown(fn() => '/search?q=%s');

	Assert::same(
		[['/search?q=%s', 'Page', true], ['/search?q=%s', 'Page', true], ['/search?q=two+words', 'two words', false]],
		describeLinks($markdown, '[Page] [Page][] [two words][page]'),
	);
});


test('the resolver may replace the text of a bare reference and set a title', function () {
	$markdown = createMarkdown(function (string $name, Nodes\LinkNode $link) {
		if ($link->isBareRef) {
			$link->content = new Nodes\ContentNode([new Nodes\TextNode('Title of ' . $name)]);
		}
		$link->modifier = new Texy\Modifier(title: 'about ' . $name);
		return '/wiki/' . $name;
	});

	Assert::same("<p><a href=\"/wiki/page\" title=\"about page\">Title of page</a></p>\n", $markdown->toHtml('[page]'));
	Assert::same("<p><a href=\"/wiki/page\" title=\"about page\">text</a></p>\n", $markdown->toHtml('[text][page]'));
});


test('the text of the link is parsed', function () {
	$markdown = createMarkdown(fn(string $name) => '/' . $name);

	Assert::same("<p><a href=\"/x\"><em>page</em></a></p>\n", $markdown->toHtml('[*page*][x]'));
});


test('an image reference never reaches the resolver', function () {
	$markdown = createMarkdown(fn() => Assert::fail('The resolver must not be asked.'));

	Assert::same("<p>![alt] ![alt][] ![alt][img]</p>\n", $markdown->toHtml('![alt] ![alt][] ![alt][img]'));
});


test('an invalid label never reaches the resolver', function () {
	$markdown = createMarkdown(fn() => Assert::fail('The resolver must not be asked.'));

	$document = $markdown->parse("[] [ ] [\n] [text][ ] [x" . str_repeat('a', 999) . ']');
	Assert::count(0, $document->find(Nodes\LinkNode::class));
});


test('link text holding a link is refused, the links inside it and after it stay', function () {
	$markdown = createMarkdown(fn(string $name) => '/' . $name);

	Assert::same(
		[['/inner', 'inner', true], ['/outer', 'outer', true], ['/u', 'a', false], ['/outer', 'outer', true]],
		describeLinks($markdown, '[[inner]][outer] [[a](/u)][outer]'),
	);
});


test('the resolver is asked once per accepted link', function () {
	$names = [];
	$markdown = createMarkdown(function (string $name) use (&$names) {
		$names[] = $name;
		return '/' . $name;
	});

	$links = $markdown->parse('[a] and [b][] and [text][c], [[[[[d]]]]] [[[e]][f]][g]')->find(Nodes\LinkNode::class);

	Assert::count(7, $links);
	Assert::same(['a', 'b', 'c', 'd', 'e', 'f', 'g'], $names);
});


test('a shared configuration', function () {
	$config = new Texy\Config;
	$config->linkResolver = fn(string $name) => '/wiki/' . $name;

	Assert::same("<p><a href=\"/wiki/page\">page</a></p>\n", new Markdown($config)->toHtml('[page]'));
	Assert::same("<p><a href=\"/wiki/page\">page</a></p>\n", Markdown::gfm($config)->toHtml('[page]'));
});


test('the safe mode filters a URL the resolver returns', function () {
	$markdown = createMarkdown(fn() => 'javascript:alert(1)');
	Configurator::safeMode($markdown);

	Assert::notContains('javascript', $markdown->toHtml('[page]'));
});


test('without a resolver an undefined reference is text', function () {
	Assert::same("<p>[page] [text][page]</p>\n", (new Markdown)->toHtml('[page] [text][page]'));
});
