<?php declare(strict_types=1);

/**
 * Test: what the shipped HTML path makes of the whole CommonMark corpus.
 *
 * A golden file, not a compliance check - Texy indents, wraps and well-forms, so this
 * output is deliberately not the reference implementation's. Regenerate with
 * `php tools/generate-html-golden.php` and read the diff.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;

require __DIR__ . '/../bootstrap.php';


test('every example renders through Texy', function () {
	$cases = loadSpec('spec-commonmark');
	$markdown = Markdown::gfm();
	$renderer = $markdown->createHtmlRenderer();
	$actual = '';

	foreach ($cases as $case) {
		$document = $markdown->parse($case['markdown']);
		$markdown->transform($document);
		$actual .= '=== ' . $case['example'] . ' ' . $case['section'] . "\n"
			. $renderer->render($document) . "\n";
	}

	Assert::same(
		normalizeNewlines(file_get_contents(__DIR__ . '/../fixtures/html-pipeline.expected')),
		normalizeNewlines($actual),
	);
});


test('rendering is a pure function of the AST', function () {
	$markdown = Markdown::gfm();
	$document = $markdown->parse("# Hi *there*\n\n- a\n- b\n\n| x | y |\n| - | - |\n| 1 | 2 |\n");
	$markdown->transform($document);
	$renderer = $markdown->createHtmlRenderer();

	Assert::same($renderer->render($document), $renderer->render($document));
});
