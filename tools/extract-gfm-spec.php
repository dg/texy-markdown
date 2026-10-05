<?php declare(strict_types=1);

/**
 * Extracts the GFM extension examples into tests/fixtures/spec-gfm.json.
 *
 * The GFM spec is a fork of the CommonMark one with the extension sections appended,
 * so only those sections are taken; the CommonMark part of it belongs to an older
 * version of that spec and would contradict the suite we already run.
 *
 * Run by hand when the tracked GFM revision is deliberately bumped - the result is
 * committed, and the test suite never downloads anything.
 *
 * Usage: php tools/extract-gfm-spec.php [spec.txt]
 */

const SpecUrl = 'https://raw.githubusercontent.com/github/cmark-gfm/master/test/spec.txt';

const Sections = [
	'Tables (extension)',
	'Task list items (extension)',
	'Strikethrough (extension)',
	'Autolinks (extension)',
	'Disallowed Raw HTML (extension)',
];

$source = $argv[1] ?? SpecUrl;
$spec = @file_get_contents($source);
if ($spec === false) {
	die("Cannot read $source\n");
}

$fence = str_repeat('`', 32);
$section = '';
$examples = [];
$number = 0;
$buffer = null;

foreach (explode("\n", str_replace("\r\n", "\n", $spec)) as $line) {
	if ($buffer === null) {
		if (preg_match('~^#{1,6} (.+)$~', $line, $m)) {
			$section = trim($m[1]);
		} elseif (str_starts_with($line, $fence . ' example')) {
			$buffer = [];
			$number++;
		}

		continue;
	}

	if (str_starts_with($line, $fence)) {
		$parts = explode("\n.\n", implode("\n", $buffer) . "\n");
		if (count($parts) === 2 && in_array($section, Sections, true)) {
			$examples[] = [
				'markdown' => $parts[0] . "\n",
				'html' => $parts[1],
				'example' => $number,
				'section' => $section,
			];
		}

		$buffer = null;
		continue;
	}

	$buffer[] = $line;
}

$file = __DIR__ . '/../tests/fixtures/spec-gfm.json';
file_put_contents($file, json_encode($examples, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

$counts = array_count_values(array_column($examples, 'section'));
foreach ($counts as $name => $count) {
	printf("%-34s %3d\n", $name, $count);
}

printf("%-34s %3d  -> %s\n", 'TOTAL', count($examples), $file);
