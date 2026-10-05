<?php declare(strict_types=1);

/**
 * Regenerates tests/fixtures/html-pipeline.expected.
 *
 * The file records what the shipped HTML path - Texy's own renderer - makes of every
 * CommonMark example. It is not a compliance check; that is what the spec suites are
 * for. It exists so that a change to the renderer handlers, or to Texy's well-forming,
 * shows up as a reviewable diff instead of silently.
 *
 * Read the diff before committing a regenerated file.
 */

use Texy\Markdown\Markdown;

if (@!include __DIR__ . '/../vendor/autoload.php') {
	die("Install packages using `composer install`\n");
}

$cases = json_decode(
	file_get_contents(__DIR__ . '/../tests/fixtures/spec-commonmark.json'),
	true,
	flags: JSON_THROW_ON_ERROR,
);

$markdown = Markdown::gfm();
$renderer = $markdown->createHtmlRenderer();
$out = '';

foreach ($cases as $case) {
	$document = $markdown->parse($case['markdown']);
	$markdown->transform($document);
	$out .= '=== ' . $case['example'] . ' ' . $case['section'] . "\n"
		. $renderer->render($document) . "\n";
}

$file = __DIR__ . '/../tests/fixtures/html-pipeline.expected';
file_put_contents($file, $out);
printf("%d examples -> %s\n", count($cases), $file);
