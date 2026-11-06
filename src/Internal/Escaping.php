<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Internal;

use Texy\Helpers;
use const ENT_HTML5, ENT_QUOTES;


/**
 * Unescaping and label normalization.
 *
 * @internal
 */
final class Escaping
{
	public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . self::class);
	}


	/**
	 * Resolves backslash escapes and HTML entities, as link destinations and titles require,
	 * in one pass, so an escaped & starts no entity.
	 */
	public static function unescape(string $s): string
	{
		$s = preg_replace_callback(
			'~\\\(' . Patterns::Escapable . ')|' . Helpers::EntityPattern . '~',
			fn($m) => $m[1] ?? html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
			$s,
		);
		return Helpers::stripControlChars($s);
	}


	/**
	 * Normalizes a link label for comparison: whitespace collapsed, full Unicode case fold.
	 * Backslash escapes are deliberately NOT resolved - `[foo\!]` must not match a
	 * definition of `[foo!]`.
	 */
	public static function normalizeLabel(string $s): string
	{
		return mb_convert_case(preg_replace('~\s+~u', ' ', trim($s)), MB_CASE_FOLD, 'UTF-8');
	}


	/** Strips the angle brackets of a pointy-bracketed destination and unescapes it. */
	public static function cleanDestination(string $dest): string
	{
		if (str_starts_with($dest, '<') && str_ends_with($dest, '>')) {
			$dest = substr($dest, 1, -1);
		}

		return self::unescape($dest);
	}


	/** Strips the quotes or parentheses of a title and unescapes it. */
	public static function cleanTitle(?string $title): ?string
	{
		return $title === null ? null : self::unescape(substr($title, 1, -1));
	}
}
