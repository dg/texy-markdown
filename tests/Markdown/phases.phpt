<?php declare(strict_types=1);

/**
 * Test: parse() returns the syntax tree, transform() turns it into the document.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\Stage;

require __DIR__ . '/../bootstrap.php';


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
