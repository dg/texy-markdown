<?php declare(strict_types=1);

/**
 * Test: Markdown converted to Texy source.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\Output\Text\Renderer as TextRenderer;

require __DIR__ . '/../bootstrap.php';


/** The text of a document as Texy reads it, without whitespace. */
function readText(Texy\Nodes\DocumentNode $document): string
{
	return preg_replace('~\s+~u', '', (new TextRenderer)->render($document));
}


test('Markdown notation becomes Texy notation', function () {
	$markdown = normalizeNewlines(<<<'MD'
		# Title

		Some *emphasis*, **strong**, `code`, ~~gone~~ and a [link](http://example.com "Example").
		An ![image](logo.png) and <https://texy.info>.

		Setext
		------

		- one
		- two

		3. three
		4. four

		> quoted

		```php
		echo 1;
		```

		***
		MD);

	Assert::same(normalizeNewlines(<<<'TEXY'
		Title
		#####

		Some //emphasis//, **strong**, `code`, <del>gone</del> and a "link .(Example)":http://example.com.
		An [* logo.png .(image) *] and https://texy.info.

		Setext
		******

		- one
		- two

		3) three
		4) four

		> quoted

		/--code php
		echo 1;
		\--

		***

		TEXY), Markdown::gfm()->toTexy($markdown));
});


test('text Texy would read as syntax is escaped', function () {
	$converted = (new Markdown)->toTexy('a "quoted":word, m^2 and \*not em\*');
	Assert::same("a &quot;quoted\":word, m&#94;2 and \\*not em*\n", $converted);
	Assert::same('a"quoted":word,m^2and*notem*', readText((new Texy\Texy)->parse($converted)));
});


test('what Texy writes no notation for', function () {
	$markdown = new Markdown;
	Assert::same("//a <em>b</em> c//\n", $markdown->toTexy('*a *b* c*'));
	Assert::same("<code>a`b</code>\n", $markdown->toTexy('``a`b``'));
	Assert::same("[link](http://a.cz)s\n", $markdown->toTexy('[link](http://a.cz)s'));
	Assert::same("link\n", $markdown->toTexy('[link]()'));
});


test('a soft break stays a newline unless the next line would read as a block', function () {
	$markdown = new Markdown;
	Assert::same("foo\nbar\n", $markdown->toTexy("foo\nbar"));
	Assert::same("foo\n2. bar\n", $markdown->toTexy("foo\n2. bar"));
	Assert::same("foo 2) bar\n", $markdown->toTexy("foo\n2) bar"));
	Assert::same("foo 1. bar\n", $markdown->toTexy("foo\n1\\. bar"));
	Assert::same("\"link\":http://a.cz 2) s\n", $markdown->toTexy("[link](http://a.cz)\n2) s"));
	Assert::same("foo [* a.png .(alt) *]\n", $markdown->toTexy("foo\n![alt](a.png)"));
	Assert::same("[* a.png .(alt) *] foo\n", $markdown->toTexy("![alt](a.png)\nfoo"));
	Assert::same("- [* a.png .(alt) *] foo\n", $markdown->toTexy("- ![alt](a.png)\n  foo"));
	Assert::same("> [* a.png .(alt) *] foo\n", $markdown->toTexy("> ![alt](a.png)\n> foo"));
});


test('Texy reads back what the Markdown says', function () {
	$source = normalizeNewlines(file_get_contents(__DIR__ . '/../../readme.md'));
	$markdown = Markdown::gfm();
	$document = $markdown->parse($source);
	$texy = new Texy\Texy;
	Assert::same(readText($document), readText($texy->parse($markdown->toTexy($source))));
});
