<?php declare(strict_types=1);

/**
 * Test: Config::$mergeLines = false makes every newline a hard line break.
 */

use Tester\Assert;
use Texy\Config;
use Texy\Markdown\Markdown;
use Texy\Markdown\Output\Markdown\Renderer;
use Texy\Nodes;

require __DIR__ . '/../bootstrap.php';


function breaksOff(bool $gfm = false): Markdown
{
	$markdown = $gfm ? Markdown::gfm() : new Markdown;
	$markdown->config->mergeLines = false;
	return $markdown;
}


test('by default a newline is a soft break', function () {
	$markdown = new Markdown;
	Assert::same("<p>a b</p>\n", $markdown->toHtml("a\nb"));
	Assert::count(1, $markdown->parse("a\nb")->find(Nodes\SoftBreakNode::class));
	Assert::count(0, $markdown->parse("a\nb")->find(Nodes\LineBreakNode::class));
});


test('every newline is a break', function () {
	$markdown = breaksOff();
	Assert::same("<p>a<br>\nb<br>\nc</p>\n", $markdown->toHtml("a\nb\nc"));
	Assert::same("<h1>a<br>\nb</h1>\n", $markdown->toHtml("a\nb\n==="));
	Assert::contains("<li>a<br>\n\tb</li>", $markdown->toHtml("- a\n  b"));
	Assert::contains("<p>a<br>\n\tb</p>", $markdown->toHtml("> a\n> b"));
	Assert::contains('>a<br>', $markdown->toHtml("[a\nb](/u)"));
});


test('a hard break stays one break', function () {
	$markdown = breaksOff();
	Assert::same("<p>a<br>\nb</p>\n", $markdown->toHtml("a  \nb"));
	Assert::same("<p>a<br>\nb</p>\n", $markdown->toHtml("a\\\nb"));
	Assert::count(1, $markdown->parse("a  \nb")->find(Nodes\LineBreakNode::class));
});


test('code is not touched', function () {
	$markdown = breaksOff();
	Assert::notContains('<br', $markdown->toHtml("```\na\nb\n```"));
	Assert::notContains('<br', $markdown->toHtml("    a\n    b"));
	Assert::notContains('<br', $markdown->toHtml("`a\nb`"));
	Assert::notContains('<br', $markdown->toHtml("<div>\na\n</div>"));
});


test('a table cell holds no newline', function () {
	Assert::notContains('<br', breaksOff(gfm: true)->toHtml("| a | b |\n| - | - |\n| c | d |"));
});


test('the image alternative text stays plain', function () {
	$html = breaksOff()->toHtml("![a\nb](/i.png)");
	Assert::contains("alt=\"a\nb\"", $html);
	Assert::notContains('<br', $html);
});


test('the hard break keeps the range of the soft one', function () {
	$source = "ab\ncd";
	$break = breaksOff()->parse($source)->find(Nodes\LineBreakNode::class)[0];
	Assert::same(2, $break->range->offset);
	Assert::same(1, $break->range->length);
});


test('a shared Config is read at every parse', function () {
	$config = new Config;
	$markdown = new Markdown($config);
	Assert::same("<p>a b</p>\n", $markdown->toHtml("a\nb"));

	$config->mergeLines = false;
	Assert::same("<p>a<br>\nb</p>\n", $markdown->toHtml("a\nb"));

	$config->mergeLines = true;
	Assert::same("<p>a b</p>\n", $markdown->toHtml("a\nb"));
});


test('the breaks print back to Markdown and Texy', function () {
	$markdown = breaksOff();
	$document = $markdown->parse("a\nb");
	$printed = (new Renderer)->render($document);
	Assert::same("a  \nb\n", $printed);
	Assert::same("<p>a<br>\nb</p>\n", (new Markdown)->toHtml($printed));

	$texy = $markdown->toTexy("a\nb");
	Assert::same("<p>a<br>\nb</p>\n", (new Texy\Texy)->toHtml($texy));
});
