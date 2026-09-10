<?php declare(strict_types=1);

/**
 * Test: where a paragraph ends.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\NodeHelpers;
use Texy\Nodes;

require __DIR__ . '/../bootstrap.php';


test('the gap continues the paragraph in front of it when a block handler refuses its match', function () {
	// a backtick fence may not have a backtick in its info string, so the handler refuses it
	$source = "foo\n``` a`b\n";
	$document = (new Markdown)->parse($source);
	$paragraphs = NodeHelpers::find($document, Nodes\ParagraphNode::class);

	Assert::count(1, $paragraphs);
	Assert::same(0, $paragraphs[0]->range->offset);
	Assert::same(strlen(rtrim($source)), $paragraphs[0]->range->length);
	Assert::count(0, NodeHelpers::find($document, Nodes\CodeBlockNode::class));
});
