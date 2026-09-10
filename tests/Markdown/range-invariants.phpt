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
	if ($range === null && $node instanceof Nodes\PhraseNode) {
		$errors[] = "$path has no range";
	} elseif ($range !== null && ($range->offset < 0 || $range->offset + $range->length > $length)) {
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


test('every line of code maps to where it is written', function () {
	$failures = [];
	foreach (loadSpec('spec-commonmark') as $case) {
		$source = Markdown::normalize($case['markdown']);
		foreach ((new Markdown)->parse($case['markdown'])->find(Nodes\CodeBlockNode::class) as $code) {
			$local = 0;
			foreach (explode("\n", $code->code) as $line) {
				$offset = $code->codeMap->toAbsolute($local);
				$written = substr($source, $offset, strlen($line));
				// a tab partly turned into spaces is longer than its source
				if ($written !== $line && !str_contains(substr($source, $code->range->offset, $code->range->length), "\t")) {
					$failures[] = "example $case[example]: " . json_encode($line) . ' maps to ' . json_encode($written);
				}

				$local += strlen($line) + 1;
			}
		}
	}

	Assert::same([], $failures);
});


test('a phrase covers its delimiters, what is left of a run only itself', function () {
	$source = 'x ***a* y';
	$document = (new Markdown)->parse($source);
	$written = fn(?Range $range) => substr($source, $range->offset, $range->length);

	Assert::same('*a*', $written($document->find(Nodes\PhraseNode::class)[0]->range));
	Assert::same('**', $written($document->find(Nodes\TextNode::class)[1]->range));
});


test('a code block of one blank line is told from an empty one by its map', function () {
	$render = fn(string $source) => (new Texy\Markdown\Tests\SpecRenderer)->render((new Markdown)->parse($source), $source);

	Assert::same("<pre><code></code></pre>\n", $render("```\n```"));
	Assert::same("<pre><code>\n</code></pre>\n", $render("```\n\n```"));
});


test('a fenced code block covers its fences', function () {
	$source = "```php\na\n```\n\n```\nb\n";
	$ranges = array_map(fn($node) => $node->range, (new Markdown)->parse($source)->find(Nodes\CodeBlockNode::class));
	Assert::same(["```php\na\n```", "```\nb"], array_map(fn(Range $r) => substr($source, $r->offset, $r->length), $ranges));
});
