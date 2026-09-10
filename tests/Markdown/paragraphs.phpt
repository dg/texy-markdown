<?php declare(strict_types=1);

/**
 * Test: where a paragraph ends.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\Nodes;

require __DIR__ . '/../bootstrap.php';


test('the gap continues the paragraph in front of it when a block handler refuses its match', function () {
	// a backtick fence may not have a backtick in its info string, so the handler refuses it
	$source = "foo\n``` a`b\n";
	$document = (new Markdown)->parse($source);
	$paragraphs = $document->find(Nodes\ParagraphNode::class);

	Assert::count(1, $paragraphs);
	Assert::same(0, $paragraphs[0]->range->offset);
	Assert::same(strlen(rtrim($source)), $paragraphs[0]->range->length);
	Assert::count(0, $document->find(Nodes\CodeBlockNode::class));
});


test('a number in another script is no list marker', function () {
	$markdown = new Markdown;
	$digit = "\u{0661}";

	Assert::count(0, $markdown->parse("$digit. a")->find(Nodes\ListNode::class));
	Assert::count(1, $markdown->parse("> a\n$digit. b")->find(Nodes\ParagraphNode::class));
	Assert::count(1, $markdown->parse("- a\n$digit. b")->find(Nodes\ListItemNode::class));
	Assert::count(1, $markdown->parse("a\n$digit. b\n===")->find(Nodes\HeadingNode::class));
});
