<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Parsing;

use Texy\Helpers;
use Texy\Nodes;
use Texy\Range;
use function strlen;


/**
 * Raw HTML as the nodes of the common AST, which the HTML policy can judge.
 *
 * @internal
 */
final class HtmlNodes
{
	/** elements whose content is no markup; true if it holds no entities either */
	private const TextElements = ['script' => true, 'style' => true, 'textarea' => false];


	public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . self::class);
	}


	/** The node of raw HTML as written; what HTML does not know as markup stays text. */
	public static function create(string $source, int $offset): Nodes\InlineNode
	{
		$range = new Range($offset, strlen($source));
		if (preg_match('~^<(/?)(' . Patterns::TagName . ')(' . Patterns::Attr . '*)[ \t\n]*(/?)>$~D', $source, $m)) {
			return new Nodes\HtmlTagNode(
				$m[2],
				self::parseAttributes($m[3]),
				closing: $m[1] === '/',
				selfClosing: $m[4] === '/',
				range: $range,
				source: $source,
			);
		}

		return str_starts_with($source, '<!--')
			? new Nodes\HtmlCommentNode(substr($source, 4, -3), $range)
			: new Nodes\RawTextNode($source, $range);
	}


	/**
	 * The first of duplicate attributes counts, as in HTML.
	 * @return array<string, string|true>
	 */
	private static function parseAttributes(string $attributes): array
	{
		preg_match_all(
			'~[ \t\n]+(' . Patterns::AttrName . ')(?:[ \t\n]*=[ \t\n]*(?|"([^"]*)"|\'([^\']*)\'|([^ \t\n"\'=<>`]+)))?~',
			$attributes,
			$matches,
			PREG_SET_ORDER,
		);

		$res = [];
		foreach ($matches as $m) {
			$res[strtolower($m[1])] ??= isset($m[2]) ? Helpers::unescapeHtml($m[2]) : true;
		}

		return $res;
	}


	/**
	 * Reads the markup of an HTML block; offsets of the nodes are local to the text.
	 */
	public static function parseBlock(string $code): Nodes\ContentNode
	{
		$nodes = [];
		$pos = 0;
		while (preg_match('~' . Patterns::RawHtml . '|' . Helpers::EntityPattern . '~', $code, $m, PREG_OFFSET_CAPTURE, $pos)) {
			[$token, $start] = $m[0];
			self::addText($nodes, substr($code, $pos, $start - $pos), $pos);
			$pos = $start + strlen($token);

			if ($token[0] === '&') {
				$nodes[] = new Nodes\TextNode(Helpers::decodeEntity($token) ?? $token, new Range($start, strlen($token)));
				continue;
			}

			$node = self::create($token, $start);
			$nodes[] = $node;
			$literal = $node instanceof Nodes\HtmlTagNode && !$node->closing
				? (self::TextElements[strtolower($node->name)] ?? null)
				: null;
			if ($literal !== null) {
				$end = preg_match('~</(?i:' . $node->name . ')[ \t\n]*>~', $code, $close, PREG_OFFSET_CAPTURE, $pos)
					? $close[0][1]
					: strlen($code);
				$content = substr($code, $pos, $end - $pos);
				if ($content !== '') {
					$nodes[] = $literal
						? new Nodes\RawTextNode($content, new Range($pos, strlen($content)))
						: new Nodes\TextNode(Helpers::decodeEntities($content), new Range($pos, strlen($content)));
				}
				$pos = $end;
			}
		}

		self::addText($nodes, substr($code, $pos), $pos);
		return new Nodes\ContentNode($nodes);
	}


	/** @param  list<Nodes\InlineNode>  $nodes */
	private static function addText(array &$nodes, string $text, int $offset): void
	{
		if ($text !== '') {
			$nodes[] = new Nodes\TextNode($text, new Range($offset, strlen($text)));
		}
	}
}
