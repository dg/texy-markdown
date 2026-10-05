<?php declare(strict_types=1);

/**
 * Scores the parser against a committed spec fixture and prints a per-section table.
 *
 * Usage:
 *   php tools/spec-score.php                  all sections, summary only
 *   php tools/spec-score.php Links            one section
 *   php tools/spec-score.php Links -v         with a diff of every failure
 *   php tools/spec-score.php -l               just the list of failing example numbers
 *   php tools/spec-score.php --gfm            score against the GFM extension fixture
 *   php tools/spec-score.php -e 565 569       show these examples, whatever their section
 */

use Texy\Markdown\Markdown;
use Texy\Markdown\Tests\SpecRenderer;

if (@!include __DIR__ . '/../vendor/autoload.php') {
	die("Install packages using `composer install`\n");
}

require __DIR__ . '/../tests/SpecRenderer.php';

$args = array_slice($argv, 1);
$verbose = in_array('-v', $args, true);
$listOnly = in_array('-l', $args, true);
$gfm = in_array('--gfm', $args, true);
$examples = [];
if (($i = array_search('-e', $args, true)) !== false) {
	$examples = array_map('intval', array_filter(array_slice($args, $i + 1), 'ctype_digit'));
}

$filter = null;
foreach ($args as $arg) {
	if (!str_starts_with($arg, '-') && !ctype_digit($arg)) {
		$filter = $arg;
		break;
	}
}

$file = __DIR__ . '/../tests/fixtures/' . ($gfm ? 'spec-gfm' : 'spec-commonmark') . '.json';
if (!is_file($file)) {
	die("Missing fixture $file\n");
}

$tests = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
$markdown = $gfm ? Markdown::gfm() : new Markdown;
$renderer = new SpecRenderer;
$sections = [];

// what gets shown: the failures, or with -e whatever was asked for
$shown = [];

foreach ($tests as $test) {
	if ($examples && !in_array($test['example'], $examples, true)) {
		continue;
	}

	if (!$examples && $filter !== null && stripos($test['section'], $filter) === false) {
		continue;
	}

	$sections[$test['section']] ??= ['pass' => 0, 'total' => 0, 'fails' => []];
	$sections[$test['section']]['total']++;

	try {
		$document = $markdown->parse($test['markdown']);
		$markdown->transform($document);
		$actual = $renderer->render($document);
	} catch (Throwable $e) {
		$actual = $e::class . ': ' . $e->getMessage();
	}

	$ok = $actual === $test['html'];
	if ($ok) {
		$sections[$test['section']]['pass']++;
	} else {
		$sections[$test['section']]['fails'][] = $test['example'];
	}

	if (!$ok || $examples) {
		$shown[] = $test + ['actual' => $actual];
	}
}

if (!$sections) {
	die("Nothing matched.\n");
}

if ($listOnly) {
	foreach ($sections as $section => $score) {
		if ($score['fails']) {
			printf("%-42s %s\n", $section, implode(', ', $score['fails']));
		}
	}

	printf("TOTAL FAILS: %d\n", count(array_merge(...array_column($sections, 'fails'))));
	exit;
}

if (!$examples) {
	$passed = $total = 0;
	printf("%-42s %5s %6s %7s\n", 'SECTION', 'PASS', 'TOTAL', '');
	foreach ($sections as $section => $score) {
		$passed += $score['pass'];
		$total += $score['total'];
		printf("%-42s %5d %6d %6.0f%%\n", $section, $score['pass'], $score['total'], $score['pass'] / $score['total'] * 100);
	}

	printf("%-42s %5d %6d %6.1f%%\n", 'TOTAL', $passed, $total, $passed / $total * 100);
}

if ($verbose || $examples) {
	foreach ($shown as $failure) {
		echo str_repeat('=', 72), "\n";
		echo "example {$failure['example']} [{$failure['section']}]\n";
		echo "--- source ---\n", str_replace("\t", '→', $failure['markdown']);
		echo "--- expected ---\n{$failure['html']}";
		echo "--- actual ---\n{$failure['actual']}";
	}
}
