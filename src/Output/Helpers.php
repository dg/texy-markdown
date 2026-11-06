<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Output;


/**
 * Static helpers for Markdown rendering.
 */
final class Helpers
{
	public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . self::class);
	}


	/**
	 * Escape special Markdown characters in text.
	 * Only escapes characters that could trigger Markdown syntax in inline context.
	 */
	public static function escapeText(string $text): string
	{
		// Escape: \ ` * _ [ ] < > ~ and the & of an entity
		// These are the main characters that trigger inline Markdown syntax, a tilde strikes through in GFM;
		// a backslash escapes only ASCII punctuation, so before anything else it stays as is (Nette\Forms),
		// and an underscore inside a word cannot open or close emphasis
		return preg_replace(
			'/([`*[\]<>~]|&(?=#?[a-zA-Z0-9]+;)|\\\\(?=[!-\/:-@[-`{-~]|$)|(?<![\p{L}\p{N}])_|_(?![\p{L}\p{N}]))/u',
			'\\\$1',
			$text,
		);
	}


	/**
	 * A line written so that it cannot start a block: its first punctuation after the indentation
	 * and the digits of a list number is escaped. Null where it has none there.
	 */
	public static function escapeBlockStart(string $line): ?string
	{
		$escaped = preg_replace('/^[ \t]*\d*\K(?=[!-\/:-@[-`{-~])/', '\\\\', $line, 1, $count);
		return $count > 0 ? $escaped : null;
	}


	/**
	 * Code span. Its content is literal, so instead of escaping it is delimited by a backtick
	 * run longer than any inside; a space keeps a leading or trailing backtick apart from it.
	 */
	public static function codeSpan(string $code): string
	{
		preg_match_all('/`+/', $code, $m);
		$fence = str_repeat('`', max(array_map(strlen(...), $m[0] ?: [''])) + 1);
		$pad = preg_match('/^`|`$|^ .*[^ ].* $/sD', $code) ? ' ' : '';
		return $fence . $pad . $code . $pad . $fence;
	}


	/**
	 * Escape URL for Markdown (spaces, parentheses).
	 */
	public static function escapeUrl(string $url): string
	{
		return str_replace(['(', ')', ' '], ['%28', '%29', '%20'], $url);
	}


	/**
	 * Escape title in link/image (double quotes).
	 */
	public static function escapeTitle(string $title): string
	{
		return str_replace('"', '\"', $title);
	}


	/**
	 * Escape alt text for images (brackets).
	 */
	public static function escapeAlt(string $alt): string
	{
		return str_replace(['[', ']'], ['\[', '\]'], $alt);
	}


	/**
	 * Escape pipe character for table cells.
	 */
	public static function escapeTableCell(string $text): string
	{
		return str_replace('|', '\|', $text);
	}


	/**
	 * Indent multi-line content for nested lists/blockquotes.
	 */
	public static function indent(string $content, int $spaces = 4): string
	{
		$indent = str_repeat(' ', $spaces);
		$lines = explode("\n", $content);
		return implode("\n", array_map(fn($line) => $line !== '' ? $indent . $line : '', $lines));
	}


	/**
	 * Prefix each line with a string (e.g., '> ' for blockquotes).
	 */
	public static function prefixLines(string $content, string $prefix): string
	{
		$lines = explode("\n", $content);
		return implode("\n", array_map(fn($line) => $prefix . $line, $lines));
	}


	/**
	 * Generate GFM table alignment separator based on modifier.
	 */
	public static function tableAlignmentSeparator(?string $hAlign): string
	{
		return match ($hAlign) {
			'left' => ':---',
			'right' => '---:',
			'center' => ':---:',
			default => '---',
		};
	}
}
