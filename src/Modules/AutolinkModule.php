<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Modules;

use Texy;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Parsing\Registry;
use Texy\Range;
use function strlen;


/**
 * `<https://example.com>` and `<user@example.com>`.
 *
 * GFM autolinks written without pointy brackets: `www.example.com`, a bare URL, a
 * bare e-mail address.
 *
 * The pattern is deliberately greedy and the handler trims what it over-matched: the
 * extension's rules about trailing punctuation and unbalanced parentheses are stated
 * as a post-processing step over the matched text, not as part of the match.
 *
 * @internal
 */
final class AutolinkModule extends Texy\Module
{
	/** A link may start only at these boundaries; nothing at all precedes the first one. */
	private const Before = '(?<![^\s*_\~(])';

	/**
	 * The repetitions are possessive throughout. The domain and the path that follows
	 * it accept overlapping characters, and a backtracking version of that pair blows
	 * the PCRE limit on any long line - which surfaces only as "Internal error".
	 */
	private const Domain = '[a-zA-Z0-9\-_]++(?:\.[a-zA-Z0-9\-_]++)++';
	private const Path = '[^\s<]*+';


	public function register(Registry $registry): void
	{
		$registry->addInline(
			Syntax::Autolink,
			'~<([a-zA-Z][a-zA-Z0-9+.-]{1,31}:[^<>[:cntrl:] ]*)>~',
			$this->parseUrl(...),
		);

		$registry->addInline(
			Syntax::AutolinkEmail,
			'~<([a-zA-Z0-9.!#$%&\'*+/=?^_`{|}\~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?'
			. '(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*+)>~',
			$this->parseEmail(...),
		);

		$registry->addInline(
			Syntax::AutolinkExtended,
			'~' . self::Before . '(?:'
			. '(?<www>www\.' . self::Domain . self::Path . ')'
			. '|(?<url>(?:https?|ftp)://' . self::Domain . self::Path . ')'
			. '|(?<mail>[a-zA-Z0-9.\-_+]++@' . self::Domain . ')'
			. ')~',
			$this->parseExtended(...),
		);
	}


	/**
	 * @param  array{string, string}  $m
	 * @param  array{int, int}  $offsets
	 */
	private function parseUrl(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		return self::createLink($m[1], $m, $offsets, Syntax::Autolink);
	}


	/**
	 * @param  array{string, string}  $m
	 * @param  array{int, int}  $offsets
	 */
	private function parseEmail(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		return self::createLink('mailto:' . $m[1], $m, $offsets, Syntax::AutolinkEmail);
	}


	/**
	 * The address between the pointy brackets is both the text and the target.
	 * @param  array{string, string}  $m
	 * @param  array{int, int}  $offsets
	 */
	private static function createLink(string $url, array $m, array $offsets, string $name): Nodes\LinkNode
	{
		$address = new Range($offsets[1], strlen($m[1]));
		return new Nodes\LinkNode(
			$url,
			new Nodes\ContentNode([new Nodes\TextNode($m[1], $address)]),
			range: new Range($offsets[0], strlen($m[0])),
			syntax: $name,
			textRange: $address,
			urlRange: $address,
		);
	}


	/**
	 * @param  array{0: string, www: ?string, url: ?string, mail: ?string}  $m
	 * @param  array{0: int, ...}  $offsets
	 */
	private function parseExtended(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode|Nodes\ContentNode|null
	{
		if ($m['mail'] !== null) {
			// a domain ending in - or _ invalidates the address, it is not trimmed off
			return preg_match('~[-_]$~', $m['mail'])
				? null
				: self::createExtendedLink('mailto:' . $m['mail'], $m['mail'], $offsets[0]);
		}

		$text = self::trimTrailing((string) ($m['www'] ?? $m['url']));
		if (!preg_match('~^(?:www\.|(?:https?|ftp)://)' . self::Domain . '~', $text)) {
			return null;
		}

		$link = self::createExtendedLink($m['www'] !== null ? 'http://' . $text : $text, $text, $offsets[0]);
		$rest = substr($m[0], strlen($text));
		if ($rest === '') {
			return $link;
		}

		// the parser consumes the whole match, so what the link gave back is emitted
		// as a sequence beside it - and parsed, because it may close an emphasis
		$sequence = new Nodes\ContentNode([$link]);
		array_push($sequence->children, ...$ctx->parseInline($rest, $offsets[0] + strlen($text))->children);
		return $sequence;
	}


	/** Drops the trailing punctuation and closing parentheses the link did not open. */
	private static function trimTrailing(string $text): string
	{
		while (true) {
			$trimmed = rtrim($text, '?!.,:*_~');
			if ($trimmed !== $text) {
				$text = $trimmed;
				continue;
			}

			// a final ) belongs to the link only if the link opened it
			if (str_ends_with($text, ')') && substr_count($text, ')') > substr_count($text, '(')) {
				$text = substr($text, 0, -1);
				continue;
			}

			// a trailing entity reference is not part of the link
			if (preg_match('~&[a-zA-Z0-9]+;$~', $text, $entity)) {
				$text = substr($text, 0, -strlen($entity[0]));
				continue;
			}

			return $text;
		}
	}


	private static function createExtendedLink(string $url, string $label, int $offset): Nodes\LinkNode
	{
		$range = new Range($offset, strlen($label));
		return new Nodes\LinkNode(
			$url,
			new Nodes\ContentNode([new Nodes\TextNode($label, $range)]),
			range: $range,
			syntax: Syntax::AutolinkExtended,
			textRange: $range,
			urlRange: $range,
		);
	}
}
