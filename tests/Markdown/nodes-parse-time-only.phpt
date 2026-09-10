<?php declare(strict_types=1);

/**
 * Test: nodes that exist only while parsing must never reach the returned AST.
 *
 * DeferredNode and DelimiterNode are scaffolding for the two-phase parse, and the
 * parse-time PendingImageNode holds an alt text that is still inline content; a leak of
 * any of them shows up as missing text or literal asterisks in every output format.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\Markdown\Parsing\DeferredNode;
use Texy\Markdown\Parsing\DelimiterNode;
use Texy\Markdown\Parsing\PendingImageNode;

require __DIR__ . '/../bootstrap.php';


$samples = [
	'plain paragraph',
	"# heading with *emphasis*\n\ntext",
	'a **b** _c_ ***d*** e_f_g',
	"- item *one*\n- item **two**\n\n  loose\n",
	"> quote with `code` and [link](/url)\n",
	"[ref] and ![img][ref]\n\n[ref]: /url \"title\"\n",
	"![alt with *markup* and a [link](/u)](/img.png \"cap\")\n",
	"unresolved [foo][bar] and ![baz]\n",
	"Setext\n======\n",
	"| not | a table |\n",
	"*unclosed and _mixed **runs\n",
];

test('no scaffolding node survives parse()', function () use ($samples) {
	$markdown = new Markdown;
	foreach ($samples as $sample) {
		$document = $markdown->parse($sample);
		Assert::null($document->findFirst(DeferredNode::class), 'DeferredNode leaked');
		Assert::null($document->findFirst(DelimiterNode::class), 'DelimiterNode leaked');
		Assert::null($document->findFirst(PendingImageNode::class), 'PendingImageNode leaked');
	}
});
