<?php declare(strict_types=1);

/**
 * Test: the GitHub Flavored Markdown extension examples.
 *
 * Only the extension sections of the GFM spec are tracked; its CommonMark part belongs
 * to an older version of that spec and is covered by spec-commonmark.phpt instead.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\Markdown\Tests\SpecRenderer;

require __DIR__ . '/../bootstrap.php';


$cases = loadSpec('spec-gfm');
$markdown = Markdown::gfm();
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

foreach (array_unique(array_column($cases, 'section')) as $section) {
	test($section, function () use ($failures, $section) {
		Assert::same([], $failures[$section] ?? []);
	});
}
