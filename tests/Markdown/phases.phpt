<?php declare(strict_types=1);

/**
 * Test: parse() returns the syntax tree, transform() turns it into the document.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\Nodes\{LinkNode, ParagraphNode};
use Texy\Parsing\ParseContext;
use Texy\Stage;

require __DIR__ . '/../bootstrap.php';


test('a block syntax reads its inline content through the context, at any depth', function () {
	$markdown = new Markdown;
	$markdown->addBlockSyntax(
		'custom/note',
		'~^!note (.+)$~',
		fn(ParseContext $context, array $m, array $offsets) => new ParagraphNode($context->parseInline($m[1], $offsets[1])),
	);

	$document = $markdown->parse("> !note [a][b]\n\n[b]: /url\n");
	Assert::same('/url', $document->findFirst(LinkNode::class)->url);
});


test('passes run in the stage they are registered for, a second transform() throws', function () {
	$markdown = new Markdown;
	$log = [];
	$markdown->addPass(function () use (&$log) { $log[] = 'transform'; });
	$markdown->addPass(function () use (&$log) { $log[] = 'syntax'; }, Stage::Syntax);

	$document = $markdown->parse('text');
	Assert::same(['syntax'], $log);

	$markdown->transform($document);
	Assert::same(['syntax', 'transform'], $log);
	Assert::exception(
		fn() => $markdown->transform($document),
		LogicException::class,
		'A document is transformed only once, this one is Done.',
	);
});
