<?php declare(strict_types=1);

/**
 * Parses a large document a few times and reports the time.
 *
 * Without an argument it builds the input by concatenating every example of the
 * CommonMark suite, which is a reasonable stand-in for the spec document itself.
 *
 * Usage: php tools/benchmark.php [file.md] [rounds]
 */

use Texy\Markdown\Markdown;
use Texy\Markdown\Tests\SpecRenderer;

if (@!include __DIR__ . '/../vendor/autoload.php') {
	die("Install packages using `composer install`\n");
}

require __DIR__ . '/../tests/SpecRenderer.php';

$file = $argv[1] ?? null;
$rounds = (int) ($argv[2] ?? 5);

if ($file !== null) {
	$text = file_get_contents($file);
} else {
	$cases = json_decode(file_get_contents(__DIR__ . '/../tests/fixtures/spec-commonmark.json'), true, flags: JSON_THROW_ON_ERROR);
	$text = implode("\n\n", array_column($cases, 'markdown'));
}

$markdown = new Markdown;
$renderer = new SpecRenderer;

$best = INF;
for ($i = 0; $i < $rounds; $i++) {
	$time = microtime(true);
	$renderer->render($markdown->parse($text));
	$best = min($best, microtime(true) - $time);
}

printf("%s KB, best of %d: %.1f ms\n", number_format(strlen($text) / 1024, 1), $rounds, $best * 1000);
