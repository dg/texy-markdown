<?php declare(strict_types=1);

/**
 * Test: nodes record the parts of their notation, as the Texy nodes do.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\Markdown\Syntax;
use Texy\NodeHelpers;
use Texy\Nodes;
use Texy\Range;

require __DIR__ . '/../bootstrap.php';


function read(string $source, ?Range $range): ?string
{
	return $range === null ? null : substr($source, $range->offset, $range->length);
}


test('links', function () {
	$source = "[inline](/a) [full][def] [def][] [def] <http://b> www.c.org\n\n[def]: /d\n";
	$links = NodeHelpers::find(Markdown::gfm()->parse($source), Nodes\LinkNode::class);

	Assert::same(
		[
			[Syntax::Link, 'inline', '/a', null],
			[Syntax::Link, 'full', 'def', 'def'],
			[Syntax::Link, 'def', 'def', 'def'],
			[Syntax::Link, 'def', 'def', 'def'],
			[Syntax::Autolink, 'http://b', 'http://b', null],
			[Syntax::AutolinkExtended, 'www.c.org', 'www.c.org', null],
		],
		array_map(fn($link) => [$link->syntax, read($source, $link->textRange), read($source, $link->urlRange), $link->ref], $links),
	);
});


test('images', function () {
	$source = '![alt](/img.png "title")';
	$image = NodeHelpers::findFirst((new Markdown)->parse($source), Nodes\ImageNode::class);
	Assert::same('/img.png', read($source, $image->urlRange));
});


test('headings', function () {
	$source = "## Atx ##\n\nSetext\n---\n";
	$headings = NodeHelpers::find((new Markdown)->parse($source), Nodes\HeadingNode::class);

	Assert::same(
		[['##', 'Atx'], ['-', 'Setext']],
		array_map(fn($heading) => [$heading->marker, read($source, $heading->textRange)], $headings),
	);
});


test('list items', function () {
	$source = "- a\n- b\n\n3) c\n";
	$items = NodeHelpers::find((new Markdown)->parse($source), Nodes\ListItemNode::class);

	Assert::same(
		[['-', '-'], ['-', '-'], ['3)', '3)']],
		array_map(fn($item) => [$item->bullet, read($source, $item->bulletRange)], $items),
	);
});


test('parts inside quotes, list items, table cells and continued lines', function () {
	$source = "> a\n> [b](/b)\n>  - [c](/c)\n>    [d](/d)\nlazy [e](/e)\n\n- x\n  > [f](/f)\n\n| [g](/g) | h \\| [i](/i) |\n| - | - |\n";
	$links = NodeHelpers::find(Markdown::gfm()->parse($source), Nodes\LinkNode::class);

	Assert::same(
		[['b', '/b'], ['c', '/c'], ['d', '/d'], ['e', '/e'], ['f', '/f'], ['g', '/g'], ['i', '/i']],
		array_map(fn($link) => [read($source, $link->textRange), read($source, $link->urlRange)], $links),
	);
});


test('containers cover all their lines', function () {
	$source = "> a\nlazy\n\n- b\n\n  c\n- d\n";
	$document = (new Markdown)->parse($source);

	Assert::same("> a\nlazy", read($source, NodeHelpers::findFirst($document, Nodes\BlockQuoteNode::class)->range));
	Assert::same(
		["- b\n\n  c", '- d'],
		array_map(fn($item) => read($source, $item->range), NodeHelpers::find($document, Nodes\ListItemNode::class)),
	);
});
