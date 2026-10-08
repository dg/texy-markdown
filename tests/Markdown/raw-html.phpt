<?php declare(strict_types=1);

/**
 * Test: raw HTML of Markdown goes through the HTML policy like the HTML of Texy.
 */

use Tester\Assert;
use Texy\Markdown\{Configurator, Markdown};
use Texy\Nodes\{HtmlBlockNode, HtmlCommentNode, HtmlTagNode, RawTextNode, TextNode};
use Texy\Output\Text\Renderer as TextRenderer;

require __DIR__ . '/../bootstrap.php';


function html(string $text, Markdown $markdown = new Markdown): string
{
	$markdown->htmlOutput->indent = false;
	$markdown->config->typography = false;
	return trim($markdown->toHtml($text));
}


test('tags become nodes with their attributes, written as they stand', function () {
	$tag = (new Markdown)->parse('a <span class="x" title=y disabled>b</span>')->findFirst(HtmlTagNode::class);
	Assert::same('span', $tag->name);
	Assert::same(['class' => 'x', 'title' => 'y', 'disabled' => true], $tag->attributes);
	Assert::same('<span class="x" title=y disabled>', $tag->source);
});


test('an entity is decoded once, in text and in an attribute', function () {
	Assert::same('<div title="a&amp;b">a &amp; b</div>', html("<div title=\"a&amp;b\">a &amp; b</div>\n"));
	Assert::same('<p><span title="a&amp;b">x</span></p>', html('<span title="a&amp;b">x</span>'));
});


test('of duplicate attributes the first counts, unquoted values are read', function () {
	Assert::same('<p><span title="a" class="b">x</span></p>', html('<span title=a TITLE=c class=b>x</span>'));
});


test('a comment passes unless the configuration says otherwise', function () {
	$markdown = new Markdown;
	Assert::same('c', $markdown->parse('a <!--c--> b')->findFirst(HtmlCommentNode::class)->text);
	Assert::same('<p>a <!--c--> b</p>', html('a <!--c--> b'));

	$markdown = new Markdown;
	$markdown->htmlOutput->passHtmlComments = false;
	Assert::same('<p>a b</p>', html('a <!--c--> b', $markdown));
});


test('script and style hold literal text, textarea entities but no tags', function () {
	Assert::same('<script>if (a<b && c) { x = "&amp;" }</script>', html("<script>if (a<b && c) { x = \"&amp;\" }</script>\n"));

	$block = (new Markdown)->parse("<style>a > b { content: \"&\" }</style>\n")->findFirst(HtmlBlockNode::class);
	Assert::same('a > b { content: "&" }', $block->findFirst(RawTextNode::class)->text);

	Assert::same('<textarea>a &amp; &lt;b&gt;</textarea>', html("<textarea>a &amp; <b></textarea>\n"));
});


test('an unknown tag is refused like in Texy, unless the policy allows it', function () {
	Assert::same('<p>&lt;foo-bar&gt;x&lt;/foo-bar&gt;</p>', html('<foo-bar>x</foo-bar>'));

	$markdown = new Markdown;
	$markdown->htmlPolicy->allowedTags = true;
	Assert::same('<p><foo-bar>x</foo-bar></p>', html('<foo-bar>x</foo-bar>', $markdown));
});


test('what is no tag is shown as text and left alone by typography', function () {
	$markdown = new Markdown;
	Assert::type(RawTextNode::class, $markdown->parse('a <?x "y"?> b')->content->children[0]->content->children[1]);
	Assert::same('<p>a &lt;?x "y"?&gt; b</p>', trim($markdown->toHtml('a <?x "y"?> b')));
	Assert::same('<p>a &lt;!X y&gt; b</p>', html('a <!X y> b'));
	Assert::same('<p>a &lt;![CDATA[&lt;b&gt;]]&gt; b</p>', html('a <![CDATA[<b>]]> b'));
});


test('a tag crossing blocks is paired around the content on output', function () {
	Assert::same(
		'<details> <summary>a</summary> <p><em>b</em></p></details>',
		html("<details>\n<summary>a</summary>\n\n*b*\n\n</details>\n"),
	);
});


test('the HTML policy judges the tags', function () {
	$markdown = new Markdown;
	$markdown->htmlPolicy->allowedTags = ['b' => []];

	Assert::same('<p><b>x</b> &lt;i&gt;y&lt;/i&gt;</p>', html('<b onclick="x()">x</b> <i>y</i>', $markdown));
	Assert::same('<p><b>x</b></p>', html("<b>x</b>\n", $markdown));
});


test('a link and an image in raw HTML are judged by the same URL policy', function () {
	$markdown = new Markdown;
	$markdown->urlPolicy->linkPattern = '~https?:~A';
	$markdown->urlPolicy->imagePattern = '~https?:~A';

	Assert::same('<p>&lt;a href="javascript:alert(1)"&gt;x&lt;/a&gt;</p>', html('<a href="javascript:alert(1)">x</a>', $markdown));
	Assert::same('&lt;img src="javascript:alert(1)"&gt;', html('<img src="javascript:alert(1)">', $markdown));
	Assert::same('<p>x</p>', html('[x](javascript:alert(1))', $markdown));
	Assert::same('<p><a href="http://x.cz">x</a></p>', html('<a href="http://x.cz">x</a>', $markdown));
});


test('the tag filter of GFM makes text of its tags', function () {
	Assert::same('<p>a &lt;script&gt;x&lt;/script&gt; <b>y</b></p>', html('a <script>x</script> <b>y</b>', Markdown::gfm()));
	Assert::same('<p>&lt;xmp&gt;<b>y</b></p>', html("<xmp><b>y</b>\n", Markdown::gfm()));

	$markdown = Markdown::gfm();
	$markdown->config->hyphenation = true;
	Assert::same(
		'<p>a &lt;iframe src="https://example.com/nejneobhospodarovavatelnejsimi"&gt;</p>',
		html('a <iframe src="https://example.com/nejneobhospodarovavatelnejsimi">', $markdown),
	);

	$markdown = Markdown::gfm();
	$markdown->addPass(
		fn(Texy\Nodes\DocumentNode $document) => $document->content->children[0]->content->children[] = new HtmlTagNode(
			'script',
			['src' => 'x'],
		),
		Texy\Stage::Syntax,
	);
	Assert::same('<p>a&lt;script src="x"&gt;</p>', html('a', $markdown));
});


test('safe mode keeps raw HTML under the whitelist', function () {
	$markdown = new Markdown;
	Configurator::safeMode($markdown);

	Assert::same('<p><b>x</b> &lt;div&gt;y&lt;/div&gt;</p>', html('<b class="c">x</b> <div>y</div>', $markdown));
	Assert::same('<p>a b</p>', html('a <!--c--> b', $markdown));
});


test('safe mode leaves the checkbox of a task', function () {
	$markdown = Markdown::gfm();
	Configurator::safeMode($markdown);
	Assert::same('<ul><li><input type="checkbox" disabled checked> a</li></ul>', html('- [x] a', $markdown));
});


test('the source of an HTML block and its markup', function () {
	$markdown = new Markdown;
	$document = $markdown->parse("> <div>\n> a\n> </div>\n");
	$block = $document->findFirst(HtmlBlockNode::class);

	Assert::same("<div>\na\n</div>", $block->code);
	Assert::same('div', $block->find(HtmlTagNode::class)[0]->name);
	$text = $block->findFirst(TextNode::class);
	Assert::same("\na\n", $text->text);
	Assert::same(7, $text->range->offset);
});


test('raw HTML is printed to Markdown and plain text', function () {
	$markdown = new Markdown;

	Assert::same("<div>\na\n</div>\n", new Texy\Markdown\Output\Markdown\Renderer()->render($markdown->parse("<div>\na\n</div>\n")));
	Assert::same("a y z\n", new TextRenderer()->render($markdown->parse('a <b class="x">y</b> z')));
});
