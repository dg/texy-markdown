<?php declare(strict_types=1);

/**
 * Test: switching individual syntaxes off through $allowed.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\Markdown\Syntax;
use Texy\Markdown\Tests\SpecRenderer;

require __DIR__ . '/../bootstrap.php';


function render(Markdown $markdown, string $text): string
{
	$document = $markdown->parse($text);
	$markdown->transform($document);
	return (new SpecRenderer)->render($document);
}


test('gfm() enables the extensions, the plain constructor does not', function () {
	$plain = new Markdown;
	$gfm = Markdown::gfm();

	Assert::same("<p>~~gone~~</p>\n", render($plain, '~~gone~~'));
	Assert::same("<p><del>gone</del></p>\n", render($gfm, '~~gone~~'));

	Assert::same("<p>www.example.com</p>\n", render($plain, 'www.example.com'));
	Assert::same(
		"<p><a href=\"http://www.example.com\">www.example.com</a></p>\n",
		render($gfm, 'www.example.com'),
	);
});


test('a GFM extension can be switched off again', function () {
	$markdown = Markdown::gfm();
	$markdown->allowed[Syntax::Strikethrough] = false;

	Assert::same("<p>~~gone~~</p>\n", render($markdown, '~~gone~~'));
	// the others are untouched
	Assert::same("<p><a href=\"http://www.example.com\">www.example.com</a></p>\n", render($markdown, 'www.example.com'));
});


test('a CommonMark syntax can be switched off', function () {
	$markdown = new Markdown;
	$markdown->allowed[Syntax::HtmlInline] = false;
	$markdown->allowed[Syntax::HtmlBlock] = false;

	Assert::same("<p>&lt;b&gt;text&lt;/b&gt;</p>\n", render($markdown, '<b>text</b>'));
});


test('indented code blocks can be switched off', function () {
	$markdown = new Markdown;
	Assert::same("<pre><code>code\n</code></pre>\n", render($markdown, "    code\n"));

	$markdown->allowed[Syntax::CodeIndented] = false;
	Assert::same("<p>code</p>\n", render($markdown, "    code\n"));
});


test('a reserved byte in the input cannot be mistaken for one the parser wrote', function () {
	$markdown = Markdown::gfm();

	// the lazy-continuation marker must not reach a block pattern
	Assert::contains('&#xFFFD;', str_replace("\u{FFFD}", '&#xFFFD;', render($markdown, "\x1Atext\n")));
});


test('parse() refuses to run inside itself', function () {
	$markdown = new Markdown;
	$markdown->addPass(function () use ($markdown) {
		$markdown->parse('nested');
	}, Texy\Stage::Syntax);

	Assert::exception(
		fn() => $markdown->parse('outer'),
		LogicException::class,
		'%A%not reentrant%A%',
	);
});


test('link reference definitions can be switched off', function () {
	$markdown = new Markdown;
	$markdown->allowed[Syntax::LinkDefinition] = false;

	Assert::same("<p>[foo]: /url</p>\n<p>[foo]</p>\n", render($markdown, "[foo]: /url\n\n[foo]"));
});


test('disabled entity syntax leaves references literal', function () {
	$markdown = new Markdown;
	$markdown->allowed[Syntax::Entity] = false;

	Assert::same("<p>a &amp;amp; b</p>\n", render($markdown, 'a &amp; b'));
});


test('an instance is reusable', function () {
	$markdown = new Markdown;
	$first = render($markdown, "[foo]\n\n[foo]: /a");
	$second = render($markdown, '[foo]');

	Assert::same("<p><a href=\"/a\">foo</a></p>\n", $first);
	Assert::same("<p>[foo]</p>\n", $second, 'definitions do not leak between parses');
});
