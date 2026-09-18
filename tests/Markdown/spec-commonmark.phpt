<?php declare(strict_types=1);

/**
 * Test: the official CommonMark 0.31.2 test suite.
 *
 * All results are computed before anything is asserted: test() offers no isolation,
 * so an assertion that fails mid-loop would stop the remaining examples from ever
 * being measured. The suite is asserted as a single score until every example passes.
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
		$markdown->transform($document);
		$actual = $renderer->render($document);
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

test('spec score', function () use ($cases, $failures) {
	$passed = count($cases) - array_sum(array_map('count', $failures));
	Assert::same(633, $passed);
});
