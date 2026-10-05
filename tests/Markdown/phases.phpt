<?php declare(strict_types=1);

/**
 * Test: parse() returns the syntax tree, transform() turns it into the document.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\Markdown\Nodes\HtmlBlockNode;
use Texy\NodeHelpers;
use Texy\Stage;

require __DIR__ . '/../bootstrap.php';


test('the disallowed raw HTML is filtered by transform()', function () {
	$markdown = Markdown::gfm();
	$document = $markdown->parse("<script>\nx\n</script>\n");
	$block = NodeHelpers::findFirst($document, fn($node) => $node instanceof HtmlBlockNode);
	Assert::same("<script>\nx\n</script>", $block->html);

	$markdown->transform($document);
	Assert::same("&lt;script>\nx\n&lt;/script>", $block->html);
});


test('passes run in the stage they are registered for, transform() only once', function () {
	$markdown = new Markdown;
	$log = [];
	$markdown->addPass(function () use (&$log) { $log[] = 'transform'; });
	$markdown->addPass(function () use (&$log) { $log[] = 'syntax'; }, Stage::Syntax);

	$document = $markdown->parse('text');
	Assert::same(['syntax'], $log);

	$markdown->transform($document);
	$markdown->transform($document);
	Assert::same(['syntax', 'transform'], $log);
});
