<?php declare(strict_types=1);

/**
 * Test: ranges of the syntax tree over every spec example: each lies in the source and in
 * the range of its parent, and siblings go in source order without overlapping.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\Node;
use Texy\Nodes;
use Texy\Range;

require __DIR__ . '/../bootstrap.php';


// a tab inside a container partly turns into spaces, so the content is longer than its source
const Skipped = [5, 6, 7];


/** @return list<string> */
function check(Node $node, ?Range $parent, int $length, string $path = ''): array
{
	$errors = [];
	$path .= '/' . substr(strrchr('\\' . $node::class, '\\'), 1);
	$range = $node->range;
	$bound = $range ?? $parent;
	if ($range !== null && ($range->offset < 0 || $range->offset + $range->length > $length)) {
		$errors[] = "$path [$range->offset, $range->length] lies outside the source";
	} elseif ($range !== null && $parent !== null && !contains($parent, $range)) {
		$errors[] = "$path [$range->offset, $range->length] lies outside its parent";
	}

	$previousEnd = null;
	foreach ($node->getChildren() as $child) {
		if ($node instanceof Nodes\ContentNode && $child->range !== null) {
			if ($previousEnd !== null && $child->range->offset < $previousEnd) {
				$errors[] = "$path: children overlap at $previousEnd";
			}

			$previousEnd = $child->range->offset + $child->range->length;
		}

		$errors = [...$errors, ...check($child, $bound, $length, $path)];
	}

	return $errors;
}


function contains(Range $outer, Range $inner): bool
{
	return $inner->offset >= $outer->offset && $inner->offset + $inner->length <= $outer->offset + $outer->length;
}


$markdown = new Markdown;
$failures = [];
foreach (loadSpec('spec-commonmark') as $case) {
	if (in_array($case['example'], Skipped, true)) {
		continue;
	}

	$errors = check($markdown->parse($case['markdown']), null, strlen(Markdown::normalize($case['markdown'])));
	if ($errors) {
		$failures[] = "example $case[example]: " . implode('; ', $errors);
	}
}

Assert::same([], $failures);
