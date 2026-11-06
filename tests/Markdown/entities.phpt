<?php declare(strict_types=1);

/**
 * Test: entity decoding starts at no escaped ampersand and never smuggles control
 * characters into the AST - they collide with the marker bytes of Texy's transform passes.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\Markdown\Tests\SpecRenderer;

require __DIR__ . '/../bootstrap.php';


function render(Markdown $markdown, string $text): string
{
	$document = $markdown->parse($text);
	$markdown->transform($document);
	return (new SpecRenderer)->render($document);
}


test('control character reference is stripped in text', function () {
	// &#12; is the only C0 reference html_entity_decode resolves into the stripped range
	Assert::same("<p>ab</p>\n", render(new Markdown, 'a&#12;b'));
});


test('control character reference is stripped in link destination and title', function () {
	Assert::same(
		"<p><a href=\"/url\" title=\"title\">a</a></p>\n",
		render(new Markdown, '[a](/u&#12;rl "ti&#12;tle")'),
	);
});


test('an escaped ampersand in link destination and title starts no entity', function () {
	Assert::same(
		"<p><a href=\"/f%C3%B6&amp;ouml;\" title=\"ö &amp;ouml;\">a</a></p>\n",
		render(new Markdown, '[a](/f&ouml;\&ouml; "&ouml; \&ouml;")'),
	);
});
