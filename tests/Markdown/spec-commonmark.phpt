<?php declare(strict_types=1);

/**
 * Test: the official CommonMark 0.31.2 test suite.
 *
 * All results are computed before anything is asserted: test() offers no isolation,
 * so an assertion that fails mid-loop would stop the remaining examples from ever
 * being measured. One assertion per section keeps the report readable.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\Markdown\Tests\SpecRenderer;

require __DIR__ . '/../bootstrap.php';


$cases = loadSpec('spec-commonmark');
$markdown = new Markdown;
$renderer = new SpecRenderer;
$failures = [];

foreach ($cases as $case) {
	try {
		$document = $markdown->parse($case['markdown']);
		$actual = $renderer->render($document, Markdown::normalize($case['markdown']));
	} catch (Throwable $e) {
		$actual = $e::class . ': ' . $e->getMessage();
	}

	if ($actual !== $case['html']) {
		$failures[$case['section']][] = sprintf(
			"example %d\n--- source ---\n%s--- expected ---\n%s--- actual ---\n%s",
			$case['example'],
			$case['markdown'],
			$case['html'],
			$actual,
		);
	}
}

foreach (array_unique(array_column($cases, 'section')) as $section) {
	test($section, function () use ($failures, $section) {
		Assert::same([], $failures[$section] ?? []);
	});
}
