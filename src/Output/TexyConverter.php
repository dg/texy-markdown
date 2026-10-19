<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Output;

use Texy\Markdown\Nodes as MdNodes;
use Texy\Markdown\Syntax as MdSyntax;
use Texy\Node;
use Texy\Nodes;
use Texy\NodeTraverser;
use Texy\Output\Text\Renderer as TextRenderer;
use Texy\Output\Texy\Renderer as TexyRenderer;
use Texy\Regexp;
use Texy\Syntax;
use Texy\Texy;
use function count, strlen;


/**
 * Prints a Markdown syntax tree as Texy source.
 *
 * The tree records Markdown notation: heading markers, bullets and link syntaxes that
 * mean something else in Texy, and text that is plain in Markdown but may read as Texy
 * syntax. The converter drops the variants, so the Texy printer writes its canonical
 * notation, and escapes the text Texy would take for syntax.
 */
final class TexyConverter
{
	/** characters that may start or end Texy syntax, all of them escaped where less is not enough */
	private const SyntaxChars = '~[*/+^_"\~><`\'\[\]{}&:()#=|\\\\-]~';

	/** reads the escaped text back, as the default Texy does */
	private Texy $texy;

	private bool $strict = false;


	public function __construct()
	{
		$this->texy = new Texy;
	}


	/**
	 * Prints the document; the document is changed on the way.
	 */
	public function convert(Nodes\DocumentNode $document): string
	{
		(new NodeTraverser)->traverse($document, leave: $this->adapt(...));

		$renderer = new TexyRenderer($this->texy);
		$renderer->addRenderer(fn(Nodes\TextNode $node) => $this->escape($node->text));
		$renderer->addRenderer(fn(MdNodes\SoftBreakNode $node) => "\n");
		$renderer->addRenderer(fn(MdNodes\RawInlineNode $node) => $node->html);
		$renderer->addRenderer(fn(MdNodes\HtmlBlockNode $node) => "/--html\n" . trim($node->html, "\n") . "\n\\--");
		$renderer->addRenderer(
			fn(Nodes\ParagraphNode $node, TexyRenderer $renderer, \Closure $previous) => $this->renderVerified($node, $renderer, $previous),
		);
		$renderer->addRenderer(
			fn(Nodes\HeadingNode $node, TexyRenderer $renderer, \Closure $previous) => $this->renderVerified($node, $renderer, $previous),
		);
		return $renderer->render($document);
	}


	/**
	 * Text escaped node by node may still read as syntax together with its neighbours,
	 * so the printed block is read back, and if its text differs, printed again with
	 * every character escaped that Texy syntax could start with.
	 * @param  \Closure(Node, TexyRenderer): string  $previous
	 */
	private function renderVerified(Nodes\BlockNode $node, TexyRenderer $renderer, \Closure $previous): string
	{
		$printed = $previous($node, $renderer);
		$expected = self::readText(new Nodes\DocumentNode(new Nodes\ContentNode([$node])));
		if ($this->strict || self::readText($this->texy->parse($printed)) === $expected) {
			return $printed;
		}

		$this->strict = true;
		try {
			return $previous($node, $renderer);
		} finally {
			$this->strict = false;
		}
	}


	/** The text of the document without whitespace, which Texy and Markdown lay out differently. */
	private static function readText(Nodes\DocumentNode $document): string
	{
		return preg_replace('~\s+~u', '', (new TextRenderer)->render($document));
	}


	/** Turns a node of Markdown notation into one the Texy printer writes in Texy notation. */
	private function adapt(Node $node): ?Node
	{
		if ($node instanceof Nodes\ContentNode) {
			$node->children = self::joinTexts($node->children);

		} elseif ($node instanceof Nodes\HeadingNode) {
			// the level of a heading in the syntax tree of Texy is counted from its notation:
			// underline characters # * = - are 0 to 3, surrounded ### and ## follow
			$node->level = max(0, $node->level - 1);
			$node->type = $node->level <= 3 ? Nodes\HeadingType::Underlined : Nodes\HeadingType::Surrounded;
			$node->marker = null;
			// and its text is one line
			(new NodeTraverser)->traverse(
				$node->content,
				fn(Node $child) => $child instanceof MdNodes\SoftBreakNode ? new Nodes\TextNode(' ') : null,
			);

		} elseif ($node instanceof Nodes\ListItemNode) {
			$node->bullet = null;

		} elseif ($node instanceof Nodes\CodeBlockNode && str_ends_with($node->code, "\n")) {
			$node->code = substr($node->code, 0, -1); // Markdown ends the code with a newline, Texy does not

		} elseif ($node instanceof Nodes\HorizontalRuleNode) {
			$node->type = $node->type === '*' ? '*' : '-';

		} elseif ($node instanceof Nodes\LinkNode) {
			return self::adaptLink($node);

		} elseif ($node instanceof Nodes\PhraseNode && $node->type === Syntax::Code) {
			// Markdown keeps the code literal in a raw node, Texy in a text node; a backtick
			// or a space at the edge does not fit between Texy backticks, the tag fits
			$code = implode('', array_map(
				fn($child) => $child instanceof Nodes\RawTextNode || $child instanceof Nodes\TextNode ? $child->text : '',
				$node->content->children,
			));
			$node->content->children = [new Nodes\TextNode($code)];
			if ($code === '' || str_contains($code, '`') || trim($code) !== $code) {
				return self::createElement('code', [], $node->content);
			}

		} elseif ($node instanceof Nodes\PhraseNode && $node->type === Syntax::Deleted) {
			// strikethrough is off in Texy by default, the tag is not
			return self::createElement('del', [], $node->content);

		} elseif ($node instanceof Nodes\PhraseNode && ($node->type === Syntax::Emphasis || $node->type === Syntax::Strong)) {
			// Texy cannot nest a phrase in the same one, the inner one is written as the tag
			(new NodeTraverser)->traverse(
				$node->content,
				fn(Node $child) => $child instanceof Nodes\PhraseNode && $child->type === $node->type
					? self::createElement($node->type === Syntax::Strong ? 'strong' : 'em', [], $child->content)
					: null,
			);
		}

		return null;
	}


	/** @param  array<string, string>  $attributes */
	private static function createElement(string $name, array $attributes, Nodes\ContentNode $content): Nodes\HtmlElementNode
	{
		return new Nodes\HtmlElementNode($name, $attributes, $content, new Nodes\HtmlTagNode($name, closing: true));
	}


	private static function adaptLink(Nodes\LinkNode $node): Nodes\InlineNode
	{
		$url = $node->url ?? '';
		$text = self::textOf($node->content);
		$first = $node->content->children[0] ?? null;

		if (in_array($node->syntax, [MdSyntax::Autolink, MdSyntax::AutolinkExtended, MdSyntax::AutolinkEmail], true)) {
			// an address Texy links by itself is written bare
			if (str_starts_with($url, 'mailto:') && $text === substr($url, 7)) {
				return new Nodes\EmailNode($text);
			} elseif ($text === $url && preg_match('~^(?:https?://|ftp://|www\.)~i', $url)) {
				return new Nodes\UrlNode($url);
			}
		}

		if ($url === '') {
			return $node; // Texy writes no link without a target, joinTexts() leaves its text

		} elseif ($node->content->children === []) {
			return self::createElement('a', ['href' => $url], $node->content); // nor without a text
		}

		// a target ending with punctuation or holding a space would end early in Texy
		$node->url = Regexp::replace(str_replace(' ', '%20', $url), '~[:);,.!?]$~', fn($m) => '%' . strtoupper(bin2hex($m[0] ?? '')));
		$node->ref = null;
		$node->syntax = match (true) {
			count($node->content->children) === 1 && $first instanceof Nodes\ImageNode => Syntax::Image,
			// the quotes of "text":url cannot hold a quote
			str_contains($text, '"') && !preg_match('~[\]|)]~', $text . $node->url) => Syntax::MarkdownLink,
			default => null,
		};
		return $node;
	}


	/**
	 * Adjacent texts are one text for escaping, a link without a target is replaced by its
	 * content, and a link whose target would run on into what follows is written as [text](url).
	 * @param  array<Nodes\InlineNode|Nodes\BlockNode>  $children
	 * @return array<Nodes\InlineNode|Nodes\BlockNode>
	 */
	private static function joinTexts(array $children): array
	{
		$flat = [];
		foreach ($children as $child) {
			array_push($flat, ...($child instanceof Nodes\LinkNode && $child->url === '' ? $child->content->children : [$child]));
		}

		$res = [];
		foreach ($flat as $child) {
			$last = count($res) - 1;
			if ($child instanceof Nodes\TextNode && ($res[$last] ?? null) instanceof Nodes\TextNode) {
				$res[$last] = new Nodes\TextNode($res[$last]->text . $child->text);
			} else {
				$res[] = $child;
			}
		}

		// the target of "text":url ends only before a space or punctuation, [text](url) anywhere
		foreach ($res as $i => $child) {
			$next = $res[$i + 1] ?? null;
			if ($child instanceof Nodes\LinkNode
				&& $child->syntax === null
				&& $next !== null
				&& !$next instanceof MdNodes\SoftBreakNode
				&& !$next instanceof Nodes\LineBreakNode
				&& !($next instanceof Nodes\TextNode && preg_match('~^[\s:);,.!?]~', $next->text))
				&& !preg_match('~[\]|]~', self::textOf($child->content))
				&& !preg_match('~[\s)]~', (string) $child->url)
			) {
				$child->syntax = Syntax::MarkdownLink;
			}
		}

		return $res;
	}


	/**
	 * Text written so that Texy reads it back as the same text: where Texy finds syntax
	 * in it, the first character of that syntax is escaped, until it finds none.
	 */
	private function escape(string $text): string
	{
		if ($this->strict) {
			return Regexp::replace($text, self::SyntaxChars, fn($m) => $m[0] === '*' ? '\*' : '&#' . ord($m[0] ?? '') . ';');
		}

		$from = 0;
		while (($offset = $this->findSyntax($text, $from)) !== null) {
			$char = mb_substr(substr($text, $offset), 0, 1);
			$escaped = $char === '*' ? '\*' : '&#' . mb_ord($char) . ';';
			$text = substr_replace($text, $escaped, $offset, strlen($char));
			$from = $offset + strlen($escaped);
		}

		return $text;
	}


	/** Where, from $from on, Texy reads the text as anything else than plain text. */
	private function findSyntax(string $text, int $from): ?int
	{
		$found = null;
		(new NodeTraverser)->traverse($this->texy->parse($text, singleLine: true), function (Node $node) use ($from, &$found): ?int {
			$range = $node->range;
			$plain = $node instanceof Nodes\DocumentNode
				|| $node instanceof Nodes\ContentNode
				|| $node instanceof Nodes\ParagraphNode
				|| $node instanceof Nodes\UrlNode
				|| $node instanceof Nodes\EmailNode
				// an entity is a text written longer than it reads
				|| ($node instanceof Nodes\TextNode && ($range === null || $range->length === strlen($node->text)));
			if ($plain || $range === null || $range->offset < $from) {
				return null;
			}

			$found = $range->offset;
			return NodeTraverser::StopTraversal;
		});
		return $found;
	}


	private static function textOf(Nodes\ContentNode $content): string
	{
		$text = '';
		foreach ($content->children as $child) {
			$text .= $child instanceof Nodes\TextNode ? $child->text : '';
		}

		return $text;
	}
}
