<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Modules;

use Texy;
use Texy\Markdown\Parsing\Escaping;
use Texy\Markdown\Parsing\ParseState;
use Texy\Markdown\Parsing\Patterns;
use Texy\Markdown\Parsing\PendingImageNode;
use Texy\Markdown\Parsing\Titles;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Parsing\Registry;
use Texy\Range;
use function strlen;


/**
 * `[text](/url "title")`, `[text][ref]`, `[ref]` and their `!` image variants.
 *
 * References resolve here, at match time, rather than in a later pass: the inline
 * phase runs after the block phase, so every definition is already known. That is
 * what makes a chain like `[foo][bar][baz]` decidable - an unresolvable reference
 * simply refuses the match, the bracket falls back to literal text and the patterns
 * behind it get their turn at the same position.
 *
 * @internal
 */
final class LinkModule extends Texy\Module
{
	public function __construct(
		private ParseState $state,
	) {
	}


	public function register(Registry $registry): void
	{
		// a destination written without pointy brackets may not begin with one either
		$dest = '(?:<(?:[^<>\n\\\]++|\\\.)*+>|(?!<)(?:[^ \t\n()\\\[:cntrl:]]++|\\\.|' . Patterns::buildNested('\(', '\)') . ')*+)';
		$tail = '(?:\((?<inl>[ \t\n]*(?<dest>' . $dest . ')(?:[ \t\n]+(?<ttl>' . Patterns::LinkTitle . '))?[ \t\n]*)\)'
			. '|(?<ref>\[(?:[^\[\]\\\]++|\\\.)*+\])?)';
		$text = '(?<brk>' . Patterns::buildNested('\[', '\]') . ')';

		// The image is registered first and starts one character earlier, so for `![x]`
		// it wins on both counts; the link pattern needs no lookbehind to stay out of
		// its way. A lookbehind would in fact be harmful - it inspects the source byte,
		// which is still `!` after an escape has consumed `\!`, and the inline parser
		// never re-searches a pattern whose candidate came back empty.
		$registry->addInline(Syntax::Image, '~!' . $text . $tail . '~', $this->parseImage(...));
		$registry->addInline(Syntax::Link, '~' . $text . $tail . '~', $this->parseLink(...));
	}


	/**
	 * @param  array{0: string, brk: string, inl: ?string, dest: ?string, ttl: ?string, ref: ?string, ...}  $m
	 * @param  array{0: int, brk: int, ...}  $offsets
	 */
	private function parseImage(ParseContext $ctx, array $m, array $offsets): ?Nodes\InlineNode
	{
		$label = substr($m['brk'], 1, -1);
		$range = new Range($offsets[0], strlen($m[0]));

		if ($m['inl'] !== null) {
			return new PendingImageNode(
				Escaping::cleanDestination($m['dest'] ?? ''),
				$ctx->parseInline($label, $offsets['brk'] + 1),
				Escaping::cleanTitle($m['ttl']),
				$range,
				self::locateTarget($m, $offsets),
			);
		}

		$definition = $this->resolve($m['ref'], $label);
		return $definition === null
			? null
			: new PendingImageNode(
				$definition['url'],
				$ctx->parseInline($label, $offsets['brk'] + 1),
				$definition['title'],
				$range,
				self::locateTarget($m, $offsets),
			);
	}


	/**
	 * @param  array{0: string, brk: string, inl: ?string, dest: ?string, ttl: ?string, ref: ?string, ...}  $m
	 * @param  array{0: int, brk: int, ...}  $offsets
	 */
	private function parseLink(ParseContext $ctx, array $m, array $offsets): ?Nodes\InlineNode
	{
		$label = substr($m['brk'], 1, -1);

		// code spans, autolinks and raw HTML take precedence: refuse, the position goes back
		if (substr_count($label, '`') % 2 || substr_count($label, '<') !== substr_count($label, '>')) {
			return null;
		}

		// an unresolvable reference is refused before its text is read
		$definition = $m['inl'] === null ? $this->resolve($m['ref'], $label) : null;
		if ($m['inl'] === null && $definition === null) {
			return null;
		}

		$textOffset = $offsets['brk'] + 1;
		$content = $this->state->readLinkText($textOffset, strlen($label), fn() => $ctx->parseInline($label, $textOffset));
		if ($content->findFirst(Nodes\LinkNode::class)) {
			return null; // links may not nest
		}

		$range = new Range($offsets[0], strlen($m[0]));
		$textRange = new Range($textOffset, strlen($label));
		if ($definition === null) {
			return new Nodes\LinkNode(
				Escaping::cleanDestination($m['dest'] ?? ''),
				$content,
				Titles::forLink(Escaping::cleanTitle($m['ttl'])),
				$range,
				syntax: Syntax::Link,
				textRange: $textRange,
				urlRange: self::locateTarget($m, $offsets),
			);
		}

		return new Nodes\LinkNode(
			$definition['url'],
			$content,
			Titles::forLink($definition['title']),
			$range,
			ref: self::getReference($m['ref'], $label),
			syntax: Syntax::Link,
			textRange: $textRange,
			urlRange: self::locateTarget($m, $offsets),
		);
	}


	/** @return ?array{url: string, title: ?string} */
	private function resolve(?string $ref, string $label): ?array
	{
		return $this->state->findDefinition(self::getReference($ref, $label));
	}


	/**
	 * The label a reference is looked up by: a full reference uses its own, a collapsed
	 * or shortcut one the link text.
	 */
	private static function getReference(?string $ref, string $label): string
	{
		return $ref !== null && $ref !== '' && $ref !== '[]' ? substr($ref, 1, -1) : $label;
	}


	/**
	 * Where the target is written: the destination, the label of a full reference, or the text.
	 * @param  array{0: string, brk: string, inl: ?string, dest: ?string, ttl: ?string, ref: ?string, ...}  $m
	 * @param  array{0: int, brk: int, ...}  $offsets
	 */
	private static function locateTarget(array $m, array $offsets): Range
	{
		return match (true) {
			$m['inl'] !== null => new Range($offsets['dest'] ?? $offsets[0], strlen($m['dest'] ?? '')),
			$m['ref'] !== null && strlen($m['ref']) > 2 => new Range(($offsets['ref'] ?? $offsets[0]) + 1, strlen($m['ref']) - 2),
			default => new Range($offsets['brk'] + 1, strlen($m['brk']) - 2),
		};
	}
}
