<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Output\Texy;

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
final class Converter
{
	/** reads the printed source, as the default Texy does */
	private Texy $texy;


	public function __construct()
	{
		$this->texy = new Texy;
	}


	/**
	 * Prints the document, which stays as it is.
	 */
	public function convert(Nodes\DocumentNode $document): string
	{
		$document = clone $document;
		(new NodeTraverser)->traverse($document, leave: $this->adapt(...));

		return new TexyRenderer($this->texy)->render($document);
	}


	/** Turns a node of Markdown notation into one the Texy printer writes in Texy notation. */
	private function adapt(Node $node): ?Node
	{
		if ($node instanceof Nodes\ContentNode) {
			$node->children = self::joinTexts($node->children);

		} elseif ($node instanceof Nodes\TextNode) {
			$node->range = null;

		} elseif ($node instanceof Nodes\HeadingNode) {
			// the four levels of underline characters, surrounded ### and ## follow
			$node->type = $node->level <= 4 ? Nodes\HeadingType::Underlined : Nodes\HeadingType::Surrounded;
			$node->marker = null;
			// and its text is one line
			(new NodeTraverser)->traverse(
				$node->content,
				fn(Node $child) => $child instanceof Nodes\SoftBreakNode ? new Nodes\TextNode(' ') : null,
			);

		} elseif ($node instanceof Nodes\ListItemNode) {
			$node->bullet = null;

		} elseif ($node instanceof Nodes\HorizontalRuleNode) {
			$node->type = $node->type === '*' ? '*' : '-';

		} elseif ($node instanceof Nodes\LinkNode) {
			return self::adaptLink($node);

		} elseif ($node instanceof Nodes\InlineCodeNode) {
			// a backtick or a space at the edge does not fit between Texy backticks, the tag fits
			if ($node->code === '' || str_contains($node->code, '`') || trim($node->code) !== $node->code) {
				return self::createElement('code', [], Nodes\ContentNode::fromText($node->code));
			}

		} elseif ($node instanceof Nodes\PhraseNode && $node->type === Nodes\PhraseType::Deleted) {
			// strikethrough is off in Texy by default, the tag is not
			return self::createElement('del', [], $node->content);

		} elseif (
			$node instanceof Nodes\PhraseNode
			&& ($node->type === Nodes\PhraseType::Emphasis || $node->type === Nodes\PhraseType::Strong)
		) {
			// Texy cannot nest a phrase in the same one, the inner one is written as the tag
			(new NodeTraverser)->traverse(
				$node->content,
				fn(Node $child) => $child instanceof Nodes\PhraseNode && $child->type === $node->type
					? self::createElement($node->type === Nodes\PhraseType::Strong ? 'strong' : 'em', [], $child->content)
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
			// an image link takes no title of the link
			count($node->content->children) === 1 && $first instanceof Nodes\ImageNode && $node->modifier?->title === null => Syntax::Image,
			// the quotes of "text":url cannot hold a quote
			str_contains($text, '"') && self::fitsBrackets($node) => Syntax::MarkdownLink,
			default => null,
		};
		return $node;
	}


	/**
	 * Adjacent texts are one text for escaping, a link without a target is replaced by its
	 * content, and a link whose target would run on into what follows is written as [text](url),
	 * or as the tag where that cannot hold it.
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

		// the target of "text":url and of an image link ends only before a space or punctuation, [text](url) anywhere
		foreach ($res as $i => $child) {
			$next = $res[$i + 1] ?? null;
			if (!$child instanceof Nodes\LinkNode
				|| ($child->syntax !== null && $child->syntax !== Syntax::Image)
				|| $next === null
				|| $next instanceof Nodes\SoftBreakNode
				|| $next instanceof Nodes\LineBreakNode
				|| ($next instanceof Nodes\TextNode && preg_match('~^[\s:);,.!?]~', $next->text))
			) {
				continue;
			}

			if ($child->syntax === null && self::fitsBrackets($child)) {
				$child->syntax = Syntax::MarkdownLink;
			} else {
				$attributes = array_filter(['href' => (string) $child->url, 'title' => $child->modifier?->title]);
				$res[$i] = self::createElement('a', $attributes, $child->content);
			}
		}

		return $res;
	}


	/** Can [text](url) write the link? Its brackets hold no ] or | of the text, no ) of the target and no title. */
	private static function fitsBrackets(Nodes\LinkNode $node): bool
	{
		return $node->modifier?->title === null
			&& !preg_match('~[\]|]~', self::textOf($node->content))
			&& !preg_match('~[\s)]~', (string) $node->url);
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
