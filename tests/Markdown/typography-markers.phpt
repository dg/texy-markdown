<?php declare(strict_types=1);

/**
 * Test: the bytes TextRunPass marks with are removed from Markdown input, so it cannot fake its markers.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\Nodes\PhraseNode;

require __DIR__ . '/../bootstrap.php';


foreach (["\x15", "\x16", "\x17"] as $byte) {
	test('a marker byte next to emphasis does not move it', function () use ($byte) {
		$markdown = new Markdown;
		$document = $markdown->parse("A{$byte}B *C* D...");
		$markdown->transform($document);

		Assert::same('C', $document->findFirst(PhraseNode::class)->content->getPlainText());
		Assert::same("AB C D\u{2026}", $document->content->getPlainText());
	});
}
