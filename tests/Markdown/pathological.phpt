<?php declare(strict_types=1);

/**
 * Test: short inputs that must not cost more than their length suggests.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;

require __DIR__ . '/../bootstrap.php';


test('nested brackets that are no link are read once', function () {
	$depth = 60; // doubling with every level, this would never end
	$source = str_repeat('[', $depth) . 'a' . str_repeat(']', $depth);

	Assert::same("<p>$source</p>\n", (new Markdown)->toHtml($source));
});


test('nested brackets of which only the innermost is a link', function () {
	$depth = 60;
	$source = str_repeat('[', $depth) . 'a' . str_repeat('](u)', $depth);

	$links = (new Markdown)->parse($source)->find(Texy\Nodes\LinkNode::class);

	Assert::count(1, $links);
	Assert::same($depth - 1, $links[0]->range->offset);
});


test('many emphasis pairs are paired in one walk', function () {
	$count = 10_000; // rebuilding the list for every pair, this takes twenty seconds
	$phrases = (new Markdown)->parse(str_repeat('*a* ', $count))->find(Texy\Nodes\PhraseNode::class);

	Assert::count($count, $phrases);
});


test('a long paragraph grows line by line without being read again', function () {
	$lines = 3000; // mapping every line again for each new one, this takes a minute
	$document = (new Markdown)->parse(str_repeat("a\n", $lines));
	$paragraphs = $document->find(Texy\Nodes\ParagraphNode::class);

	Assert::count(1, $paragraphs);
	Assert::same($lines * 2 - 1, $paragraphs[0]->range->length);
	Assert::same(($lines - 1) * 2, $document->find(Texy\Nodes\TextNode::class)[$lines - 1]->range->offset);
});


test('quotes nest to the depth the engine allows, the rest is their text', function () {
	$document = (new Markdown)->parse(str_repeat('> ', 5000) . 'a');

	Assert::count(Texy\Parsing\ParseContext::MaxDepth, $document->find(Texy\Nodes\BlockQuoteNode::class));
});


test('a paragraph of thousands of lines and a line of thousands of stars are read', function () {
	// a backtracking pattern of the setext heading or of the thematic break exhausts the stack of PCRE on both
	$markdown = new Markdown;

	Assert::count(1, $markdown->parse(str_repeat("a\n", 20_000))->find(Texy\Nodes\ParagraphNode::class));
	Assert::count(1, $markdown->parse(str_repeat("a\n", 20_000) . '===')->find(Texy\Nodes\HeadingNode::class));
	Assert::count(1, $markdown->parse(str_repeat('*', 20_000))->find(Texy\Nodes\HorizontalRuleNode::class));
	Assert::count(1, $markdown->parse(str_repeat('- ', 20_000))->find(Texy\Nodes\HorizontalRuleNode::class));
});


test('long repetitions inside one construct are read', function () {
	// a backtracking repetition exhausts the stack or the backtracking limit of PCRE on each of them
	$markdown = new Markdown;
	$count = fn(string $source, string $class) => count($markdown->parse($source)->find($class));

	Assert::same(1, $count('# a' . str_repeat(' ', 20_000) . 'b' . str_repeat(' #', 2000), Texy\Nodes\HeadingNode::class));
	Assert::same(1, $count('<a ' . str_repeat('b ', 20_000) . '>', Texy\Nodes\HtmlBlockNode::class));
	Assert::same(1, $count('x <a ' . str_repeat('b ', 20_000) . '>', Texy\Nodes\HtmlTagNode::class));
	Assert::same(1, $count('x <!--' . str_repeat('-a', 20_000) . '-->', Texy\Nodes\HtmlCommentNode::class));
	Assert::same(1, $count('[a](u "' . str_repeat('\"', 20_000) . '")', Texy\Nodes\LinkNode::class));
	Assert::same(1, $count('<a@' . str_repeat('a.', 20_000) . 'a>', Texy\Nodes\LinkNode::class));
	Assert::same(1, $count('[a]: <' . str_repeat('b', 50_000) . ">\n\n[a]", Texy\Nodes\LinkNode::class));
});


test('brackets and parentheses nest to a limit, deeper ones are text', function () {
	$markdown = new Markdown;
	$depth = Texy\Markdown\Parsing\Patterns::MaxNesting;
	$parens = fn(int $depth) => '[a](' . str_repeat('(', $depth) . str_repeat(')', $depth) . ')';

	Assert::count(1, $markdown->parse($parens($depth))->find(Texy\Nodes\LinkNode::class));
	Assert::count(0, $markdown->parse($parens($depth + 1))->find(Texy\Nodes\LinkNode::class));
	Assert::count(0, $markdown->parse('[a](' . str_repeat('(', 20_000))->find(Texy\Nodes\LinkNode::class));
	Assert::count(0, $markdown->parse(str_repeat('[', 20_000) . 'a' . str_repeat(']', 20_000))->find(Texy\Nodes\LinkNode::class));
});


test('the closing sequence of an ATX heading', function () {
	$heading = fn(string $source) => (new Markdown)->parse($source)->find(Texy\Nodes\HeadingNode::class)[0]->content->getPlainText();

	Assert::same('foo', $heading('# foo ##  '));
	Assert::same('', $heading('### ###'));
	Assert::same('foo#', $heading('# foo#'));
	Assert::same('foo ###', $heading('# foo \###'));
	Assert::same('foo\\', $heading('# foo\ #'));
	Assert::same('# #', $heading('# # # #')); // only one closing sequence is cut off
	Assert::same('a  #', $heading("#####\ta  # ##"));
});


test('a setext heading ends at its first underline', function () {
	$markdown = new Markdown;

	Assert::same("<h1>Foo</h1>\n\n<p>===</p>\n", $markdown->toHtml("Foo\n===\n==="));
	Assert::same("<h1>===</h1>\n", $markdown->toHtml("===\n==="));
});


test('the text of a link is not shared between two paragraphs', function () {
	$document = (new Markdown)->parse("[*a*](u)\n\n[*a*](u)");
	$texts = $document->find(Texy\Nodes\TextNode::class);

	Assert::count(2, $texts);
	Assert::notSame($texts[0], $texts[1]);
	Assert::same(2, $texts[0]->range->offset);
	Assert::same(12, $texts[1]->range->offset);
});
