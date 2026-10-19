<?php declare(strict_types=1);

/**
 * Test: the guarantees Markdown shares with Texy.
 */

use Tester\Assert;
use Texy\{InvalidArgumentException, InvalidInputException};
use Texy\Markdown\Markdown;
use Texy\Markdown\Output\Markdown\Renderer;
use Texy\Markdown\Output\Texy\Converter;
use Texy\Markdown\Syntax;
use Texy\Nodes\{CodeBlockNode, ContentNode, DocumentNode, ItemParagraphs, ListItemNode, ListNode, ParagraphNode, PhraseNode, TextNode};
use Texy\Output\Text\Renderer as TextRenderer;
use Texy\Parsing\ParseContext;
use Texy\{Stage, Texy};

require __DIR__ . '/../bootstrap.php';


test('a document is transformed once, whichever language asks for it', function () {
	$markdown = new Markdown;
	$document = $markdown->parse('a');
	$markdown->transform($document);

	Assert::exception(
		fn() => (new Texy)->transform($document),
		LogicException::class,
		'A document is transformed only once, this one is Done.',
	);
	Assert::exception(
		fn() => (new Markdown)->transform($document),
		LogicException::class,
		'A document is transformed only once, this one is Done.',
	);

	$texy = new Texy;
	$document = $texy->transform($texy->parse('a'));
	Assert::exception(
		fn() => (new Markdown)->transform($document),
		LogicException::class,
		'A document is transformed only once, this one is Done.',
	);
});


test('a document is transformed in the language that parsed it', function () {
	Assert::exception(
		fn() => (new Texy)->transform((new Markdown)->parse('## a')),
		LogicException::class,
		'The document was parsed as Markdown, transform it in that language.',
	);
	Assert::exception(
		fn() => (new Markdown)->transform((new Texy)->parse('a')),
		LogicException::class,
		'The document was parsed as Texy, transform it in that language.',
	);
	Assert::contains('<h2>', (new Markdown)->renderHtml((new Markdown)->transform((new Markdown)->parse('## a'))));
});


test('passes of a document run in Markdown as in Texy, typography exactly once', function () {
	$markdown = new Markdown;
	$log = [];
	$markdown->addPass(function (DocumentNode $document) use (&$log) { $log[] = $document->content->getPlainText(); });
	$markdown->toHtml('"a"');
	Assert::same(['"a"'], $log);

	$markdown->addPass(
		function (DocumentNode $document) use (&$log) { $log[] = $document->content->getPlainText(); },
		Stage::Finalize,
	);
	$log = [];
	$markdown->toHtml('"a"');
	Assert::same(['"a"', "\u{201C}a\u{201D}"], $log);
});


test('the map of syntaxes is complete and checked', function () {
	$markdown = new Markdown;
	Assert::true($markdown->allowedSyntax[Syntax::CodeIndented]);
	Assert::false($markdown->allowedSyntax[Syntax::Table]);

	$markdown->allowedSyntax['imag'] = false;
	Assert::exception(fn() => $markdown->parse('a'), InvalidArgumentException::class, "Unknown syntax 'imag', did you mean 'image'?");
	Assert::exception(
		fn() => $markdown->readsAsBlock('a'),
		InvalidArgumentException::class,
		"Unknown syntax 'imag', did you mean 'image'?",
	);
});


test('invalid UTF-8 is refused as in Texy', function () {
	Assert::exception(fn() => (new Markdown)->parse("a\xFFb"), InvalidInputException::class, 'Input is not valid UTF-8 text.');
	Assert::exception(fn() => (new Texy)->parse("a\xFFb"), InvalidInputException::class, 'Input is not valid UTF-8 text.');
});


test('a code block and a phrase have the same shape in both languages', function () {
	foreach ([new Texy, new Markdown] as $language) {
		$source = "```\na\n  b\n```\n\nx *y* z\n";
		$document = $language->parse($source);
		$code = $document->find(CodeBlockNode::class)[0];
		$phrase = $document->find(PhraseNode::class)[0];

		Assert::same("a\n  b", $code->code, $language::class);
		Assert::same(6, $code->codeMap?->toAbsolute(2), $language::class);
		Assert::same('*y*', substr($source, $phrase->range->offset, $phrase->range->length), $language::class);
	}
});


test('a syntax of the application precedes the built-in ones', function () {
	$markdown = new Markdown;
	$markdown->addInlineSyntax('custom/strong', '~\*\*a\*\*~', fn(ParseContext $context) => new TextNode('WON'));
	Assert::same('<p>WON</p>', trim($markdown->toHtml('**a**')));
});


test('the definitions of a document do not leak to the next one', function () {
	$markdown = new Markdown;
	Assert::contains('/url', $markdown->toHtml("[a]\n\n[a]: /url"));
	Assert::notContains('/url', $markdown->toHtml('[a]'));
});


test('a reference defined after its use is resolved, an unknown one stays text', function () {
	$html = (new Markdown)->toHtml("[a][b][c]\n\n[b]: /b\n[c]: /c");
	Assert::contains('<a href="/b">a</a>', $html);
	Assert::contains('[c]', str_replace('<a href="/c">c</a>', '[c]', $html));
});


test('a list in the Markdown output', function () {
	$list = fn(ItemParagraphs $paragraphs, array $items) => new DocumentNode(new ContentNode(
		[new ListNode($items, paragraphs: $paragraphs)],
	));
	$item = fn(?bool $checked = null, string ...$texts) => new ListItemNode(new ContentNode(array_map(
		fn($text) => new ParagraphNode(new ContentNode([new TextNode($text)])),
		$texts,
	)), checked: $checked);

	$render = fn(DocumentNode $document) => (new Renderer)->render($document);
	Assert::same("- a\n- b\n", $render($list(ItemParagraphs::Unwrapped, [$item(null, 'a'), $item(null, 'b')])));
	Assert::same("- a\n\n- b\n", $render($list(ItemParagraphs::Wrapped, [$item(null, 'a'), $item(null, 'b')])));
	Assert::same("- a\n- b\n", $render($list(ItemParagraphs::FirstUnwrapped, [$item(null, 'a'), $item(null, 'b')])));
	Assert::same("- a\n\n  b\n", $render($list(ItemParagraphs::FirstUnwrapped, [$item(null, 'a', 'b')])));
	Assert::same("- [x] a\n- [ ] b\n", $render($list(ItemParagraphs::Unwrapped, [$item(true, 'a'), $item(false, 'b')])));
});


test('a task keeps its state on the way through the outputs', function () {
	$markdown = Markdown::gfm();
	$document = $markdown->parse("- [x] a\n- [ ] b\n");
	$items = $document->find(ListItemNode::class);
	Assert::true($items[0]->checked);
	Assert::false($items[1]->checked);

	Assert::same("[x] a\n[ ] b\n", (new TextRenderer)->render($document));
	Assert::same("- [x] a\n- [ ] b\n", $markdown->toTexy("- [x] a\n- [ ] b\n"));
	Assert::same("- [x] a\n- [ ] b\n", (new Renderer)->render($document));
});


test('the conversion to Texy leaves the given document as it is', function () {
	$markdown = new Markdown;
	$document = $markdown->parse("# a *b*\n\n- c\n");
	$before = serialize($document);

	(new Converter)->convert($document);
	Assert::same($before, serialize($document));
});
