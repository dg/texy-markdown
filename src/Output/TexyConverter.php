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
use Texy\Output\Texy\Renderer as TexyRenderer;
use Texy\Regexp;
use Texy\Syntax;
use Texy\Texy;
use function count;


/**
 * Prints a Markdown syntax tree as Texy source.
 *
 * The tree records Markdown notation: heading markers, bullets and link syntaxes that
 * mean something else in Texy, and the positions of texts, which the Texy printer would
 * take for Texy notation. The converter drops them, so the printer writes its canonical
 * notation and escapes the text Texy would take for syntax.
 */
final class TexyConverter
{
	/** reads the printed source, as the default Texy does */
	private Texy $texy;


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
		$renderer->addRenderer(fn(MdNodes\SoftBreakNode $node) => "\n");
		$renderer->addRenderer(fn(MdNodes\RawInlineNode $node) => $node->html);
		$renderer->addRenderer(fn(MdNodes\HtmlBlockNode $node) => "/--html\n" . trim($node->html, "\n") . "\n\\--");
		return $renderer->render($document);
	}


	/** Turns a node of Markdown notation into one the Texy printer writes in Texy notation. */
	private function adapt(Node $node): ?Node
	{
		if ($node instanceof Nodes\ContentNode) {
			$node->children = self::joinTexts($node->children);

		} elseif ($node instanceof Nodes\TextNode) {
			$node->range = null;

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


	private static function textOf(Nodes\ContentNode $content): string
	{
		$text = '';
		foreach ($content->children as $child) {
			$text .= $child instanceof Nodes\TextNode ? $child->text : '';
		}

		return $text;
	}
}
