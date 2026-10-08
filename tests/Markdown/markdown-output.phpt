<?php declare(strict_types=1);

/**
 * Test: the Markdown output renderer, over documents parsed by Texy and by Markdown.
 */

use Tester\Assert;
use Texy\Markdown\Markdown;
use Texy\Markdown\Output\Renderer;

require __DIR__ . '/../bootstrap.php';


test('a fenced code block survives the round trip', function () {
	$texy = new Texy\Texy;
	$document = $texy->parse("```php\n\$x = 1;\n```");
	$texy->transform($document);
	$markdown = (new Renderer)->render($document);
	Assert::same("```php\n\$x = 1;\n```\n", $markdown);

	// and back
	Assert::same("<pre class=\"php\"><code>\$x = 1;</code></pre>\n", (new Texy\Texy)->process($markdown));
});


test('a table with a caption falls back to an HTML table', function () {
	$texy = new Texy\Texy;
	$render = function (string $s) use ($texy): string {
		$document = $texy->parse($s);
		$texy->transform($document);
		return (new Renderer)->render($document);
	};

	Assert::same(
		"<table>\n<caption>Caption</caption>\n<tr><td>a</td><td>b</td></tr>\n</table>\n",
		$render("|## Caption\n| a | b\n"),
	);
	// without a caption GFM syntax is enough
	Assert::same("|  |  |\n| --- | --- |\n| a | b |\n", $render("| a | b\n"));
});


test('soft breaks, HTML blocks and raw inline HTML of a Markdown document survive the round trip', function () {
	$markdown = new Markdown;
	$source = "first\nsecond\n\n<div>\n*block*\n</div>\n\na <b class=\"x\">bold</b> <!-- c --> word\n";
	$document = $markdown->parse($source);
	$markdown->transform($document);

	Assert::same($source, (new Renderer)->render($document));
});


function toMarkdown(string $text): string
{
	$texy = new Texy\Texy;
	$ast = $texy->parse($text);
	$texy->transform($ast);
	return (new Renderer)->render($ast);
}


// Paragraphs
test('paragraph', function () {
	Assert::same(
		"Hello world.\n",
		toMarkdown('Hello world.'),
	);
});


test('multiple paragraphs', function () {
	Assert::same(
		"First paragraph.\n\nSecond paragraph.\n",
		toMarkdown("First paragraph.\n\nSecond paragraph."),
	);
});


// Headings
test('heading ATX style', function () {
	// Texy uses dynamic balancing - first heading becomes level 1
	Assert::same(
		"# Heading 1\n",
		toMarkdown("Heading 1\n*********"),
	);
});


test('heading setext style', function () {
	$texy = new Texy\Texy;
	$texy->headingModule->top = 1;
	$texy->headingModule->levels = ['#' => 0, '*' => 0];  // * = level 1
	$ast = $texy->parse("Heading 1\n*********");
	$texy->transform($ast);
	$generator = new Renderer;
	$generator->headingStyle = 'setext';
	Assert::same(
		"Heading 1\n=========\n",
		$generator->render($ast),
	);
});


// Phrases
test('strong', function () {
	Assert::same(
		"**bold**\n",
		toMarkdown('**bold**'),
	);
});


test('emphasis', function () {
	Assert::same(
		"*italic*\n",
		toMarkdown('//italic//'),
	);
});


test('code', function () {
	Assert::same(
		"`code`\n",
		toMarkdown('`code`'),
	);
});


test('code content is literal, not escaped', function () {
	Assert::same(
		"`Nette\\Forms\\Form` and `[int, *]` and `'video/*'`\n",
		toMarkdown("`Nette\\Forms\\Form` and `[int, *]` and `'video/*'`"),
	);
});


test('code containing backticks gets a longer fence', function () {
	Assert::same('``a`b``', Texy\Markdown\Output\Helpers::codeSpan('a`b'));
	Assert::same('```a``b ```', Texy\Markdown\Output\Helpers::codeSpan('a``b '));
	Assert::same('`` `a ``', Texy\Markdown\Output\Helpers::codeSpan('`a'));
	Assert::same('`  a  `', Texy\Markdown\Output\Helpers::codeSpan(' a '));
});


test('code in a table cell keeps the pipe escaped', function () {
	Assert::same(
		"| `int\\|float` |\n| --- |\n",
		toMarkdown("| `int\\|float`\n|---"),
	);
});


test('strikethrough (GFM)', function () {
	$texy = new Texy\Texy;
	$texy->allowed[Texy\Syntax::Deleted] = true;
	$ast = $texy->parse('--deleted--');
	$texy->transform($ast);
	$generator = new Renderer;
	Assert::same(
		"~~deleted~~\n",
		$generator->render($ast),
	);
});


// Links
test('inline link', function () {
	Assert::same(
		"[link](https://example.com)\n",
		toMarkdown('"link":https://example.com'),
	);
});


test('reference link', function () {
	$texy = new Texy\Texy;
	$ast = $texy->parse('"link":https://example.com');
	$texy->transform($ast);
	$generator = new Renderer;
	$generator->linkStyle = 'reference';
	Assert::same(
		"[link][1]\n\n\n[1]: https://example.com\n",
		$generator->render($ast),
	);
});


// Images
test('image', function () {
	Assert::same(
		"![](image.png)\n",
		toMarkdown('[* image.png *]'),
	);
});


test('image with alt', function () {
	Assert::same(
		"![Alt text](image.png)\n",
		toMarkdown('[* image.png .(Alt text) *]'),
	);
});


// Lists
test('unordered list', function () {
	Assert::same(
		"- First\n- Second\n- Third\n",
		toMarkdown("- First\n- Second\n- Third"),
	);
});


test('ordered list', function () {
	Assert::same(
		"1. First\n2. Second\n3. Third\n",
		toMarkdown("1) First\n2) Second\n3) Third"),
	);
});


// Code blocks
test('fenced code block', function () {
	$input = "/--code php\n\$x = 1;\n\\--";
	Assert::same(
		"```php\n\$x = 1;\n```\n",
		toMarkdown($input),
	);
});


test('indented code block', function () {
	$texy = new Texy\Texy;
	$ast = $texy->parse("/--code\n\$x = 1;\n\\--");
	$texy->transform($ast);
	$generator = new Renderer;
	$generator->codeBlockStyle = 'indented';
	Assert::same(
		"    \$x = 1;\n",
		$generator->render($ast),
	);
});


// Blockquote
test('blockquote', function () {
	Assert::same(
		"> Quoted text.\n",
		toMarkdown('> Quoted text.'),
	);
});


// Tables (GFM)
test('table', function () {
	$input = "| A | B |\n|---\n| 1 | 2 |\n| 3 | 4 |";
	$expected = "| A | B |\n| --- | --- |\n| 1 | 2 |\n| 3 | 4 |\n";
	Assert::same($expected, toMarkdown($input));
});


test('table without a header gets an empty one', function () {
	$input = "| 1 | 2 |\n| 3 | 4 |";
	$expected = "|  |  |\n| --- | --- |\n| 1 | 2 |\n| 3 | 4 |\n";
	Assert::same($expected, toMarkdown($input));
});


test('table with colspan falls back to HTML', function () {
	$input = "| Name || Age\n| Jesus | Christ | 33";
	$expected = "<table>\n"
		. "<tr><td colspan=\"2\">Name</td><td>Age</td></tr>\n"
		. "<tr><td>Jesus</td><td>Christ</td><td>33</td></tr>\n"
		. "</table>\n";
	Assert::same($expected, toMarkdown($input));
});


test('table with rowspan falls back to HTML', function () {
	$input = "| Bill | 50\n|^ | 52";
	$expected = "<table>\n"
		. "<tr><td rowspan=\"2\">Bill</td><td>50</td></tr>\n"
		. "<tr><td>52</td></tr>\n"
		. "</table>\n";
	Assert::same($expected, toMarkdown($input));
});


// Horizontal rule
test('horizontal rule', function () {
	Assert::same(
		"---\n",
		toMarkdown('----'),
	);
});


// Line break (Texy syntax: space at the beginning of next line)
test('line break', function () {
	Assert::same(
		"First line  \nsecond line.\n",
		toMarkdown("First line\n second line."),
	);
});


// Autolinks
test('URL autolink', function () {
	Assert::same(
		"<https://example.com>\n",
		toMarkdown('https://example.com'),
	);
});


test('email autolink', function () {
	Assert::same(
		"<test@example.com>\n",
		toMarkdown('test@example.com'),
	);
});


// Special characters escaping
test('escape special characters', function () {
	Assert::same(
		"\\*not bold\\*\n",
		toMarkdown("''*not bold*''"),
	);
});


test('underscore inside a word is not escaped', function () {
	Assert::same(
		"E_USER_WARNING and \\_emphasis\\_ and snake_case\\_\n",
		toMarkdown("''E_USER_WARNING and _emphasis_ and snake_case_''"),
	);
});


test('backslash is escaped only before punctuation', function () {
	Assert::same(
		"Nette\\Forms\\Form and a\\\\\\*b and end\\\\\n",
		toMarkdown("''Nette\\Forms\\Form and a\\*b and end\\''"),
	);
});


test('pre block keeps text, not its HTML', function () {
	Assert::same(
		"```\napp/\n└── Model/ &lt; x\n```\n",
		toMarkdown("/--pre\n<b>app/</b>\n└── <b>Model/</b> &amp;lt; x\n\\--"),
	);
});


// HTML passthrough
test('HTML passthrough', function () {
	Assert::same(
		"<span>text</span>\n",
		toMarkdown('<span>text</span>'),
	);
});


// Emoticons
test('emoticons', function () {
	$texy = new Texy\Texy;
	$texy->allowed[Texy\Syntax::Emoticon] = true;
	$ast = $texy->parse(':-)');
	$texy->transform($ast);
	$generator = new Renderer;
	Assert::same(
		"🙂\n",
		$generator->render($ast),
	);
});


// URL scheme filtering (security)
test('UrlPolicy filters dangerous schemes', function () {
	$texy = new Texy\Texy;
	Texy\Configurator::safeMode($texy);
	$ast = $texy->parse('"click":javascript:evil and "ok":https://example.com');
	$texy->transform($ast);
	$generator = new Renderer($texy->urlPolicy);
	Assert::same(
		"click and [ok](https://example.com)\n",
		$generator->render($ast),
	);
});


test('without UrlPolicy no scheme filtering happens', function () {
	$texy = new Texy\Texy;
	$ast = $texy->parse('"click":javascript:evil');
	$texy->transform($ast);
	$generator = new Renderer;
	Assert::same(
		"[click](javascript:evil)\n",
		$generator->render($ast),
	);
});


test('UrlPolicy filters dangerous image schemes and leaves the escaped alternative text', function () {
	$texy = new Texy\Texy;
	$texy->urlPolicy->imagePattern = '~https?:~A';
	$texy->imageModule->addDefinition('x', 'javascript:alert(1)', alt: 'Logo <b>');
	$ast = $texy->parse('a [*x*] b and [* http://x.cz/a.jpg *]');
	$texy->transform($ast);
	$generator = new Renderer($texy->urlPolicy);
	Assert::same(
		"a Logo \\<b\\> b and ![](http://x.cz/a.jpg)\n",
		$generator->render($ast),
	);
});


test('without UrlPolicy no image scheme filtering happens', function () {
	$texy = new Texy\Texy;
	$ast = $texy->parse('a [* javascript:evil *] b');
	$texy->transform($ast);
	$generator = new Renderer;
	Assert::same(
		"a ![](javascript:evil) b\n",
		$generator->render($ast),
	);
});


test('image node without URL renders as its alternative text', function () {
	$texy = new Texy\Texy;
	$texy->addLineSyntax('myimage', '~@@~', fn() => new Texy\Nodes\ImageNode(modifier: Texy\Modifier::parse('(Logo)')));
	$ast = $texy->parse('a @@ b');
	$texy->transform($ast);
	$generator = new Renderer;
	Assert::same(
		"a Logo b\n",
		$generator->render($ast),
	);
});
