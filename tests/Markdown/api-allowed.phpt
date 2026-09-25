<?php declare(strict_types=1);

/**
 * Test: switching individual syntaxes off through $allowedSyntax.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\Markdown\Syntax;
use Texy\Markdown\Tests\SpecRenderer;

require __DIR__ . '/../bootstrap.php';


function render(Markdown $markdown, string $text): string
{
	return (new SpecRenderer)->render($markdown->parse($text), Markdown::normalize($text));
}


test('a CommonMark syntax can be switched off', function () {
	$markdown = new Markdown;
	$markdown->allowedSyntax[Syntax::HtmlInline] = false;
	$markdown->allowedSyntax[Syntax::HtmlBlock] = false;

	Assert::same("<p>&lt;b&gt;text&lt;/b&gt;</p>\n", render($markdown, '<b>text</b>'));
});


test('indented code blocks can be switched off', function () {
	$markdown = new Markdown;
	Assert::same("<pre><code>code\n</code></pre>\n", render($markdown, "    code\n"));

	$markdown->allowedSyntax[Syntax::CodeIndented] = false;
	Assert::same("<p>code</p>\n", render($markdown, "    code\n"));
});


test('a reserved byte in the input cannot be mistaken for one the parser wrote', function () {
	$markdown = new Markdown;

	// the lazy-continuation marker must not reach a block pattern
	Assert::same("<p>text</p>\n", render($markdown, "\x1Atext\n"));
});


test('control characters are removed as in Texy, NUL is replaced, a form feed is whitespace', function () {
	Assert::same("abc\u{FFFD}\tde\ff\n", Markdown::normalize("a\x01b\x15c\0\td\ve\ff\r\n"));
	Assert::notContains('<em>', (new Markdown)->toHtml("*\ffoo*"));
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
	$markdown->allowedSyntax[Syntax::LinkDefinition] = false;

	Assert::same("<p>[foo]: /url</p>\n<p>[foo]</p>\n", render($markdown, "[foo]: /url\n\n[foo]"));
});


test('disabled entity syntax leaves references literal', function () {
	$markdown = new Markdown;
	$markdown->allowedSyntax[Syntax::Entity] = false;

	Assert::same("<p>a &amp;amp; b</p>\n", render($markdown, 'a &amp; b'));
});


test('an instance is reusable', function () {
	$markdown = new Markdown;
	$first = render($markdown, "[foo]\n\n[foo]: /a");
	$second = render($markdown, '[foo]');

	Assert::same("<p><a href=\"/a\">foo</a></p>\n", $first);
	Assert::same("<p>[foo]</p>\n", $second, 'definitions do not leak between parses');
});
