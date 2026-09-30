<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Internal;


/**
 * Indentation arithmetic in columns.
 *
 * A tab advances to the next multiple of four columns, so indentation cannot be
 * measured in bytes. Expanding tabs up front is not an option either - it would
 * corrupt the content of code blocks.
 *
 * @internal
 */
final class Indentation
{
	public const TabStop = 4;


	public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . self::class);
	}


	/**
	 * Width of the line's leading whitespace in columns, counted from $startColumn.
	 */
	public static function width(string $line, int $startColumn = 0): int
	{
		$column = $startColumn;
		for ($i = 0; $i < strspn($line, " \t"); $i++) {
			$column += $line[$i] === "\t" ? self::TabStop - $column % self::TabStop : 1;
		}

		return $column - $startColumn;
	}


	/**
	 * Removes $columns columns of indentation.
	 *
	 * What is left of the indentation comes back as spaces rather than as the original
	 * bytes: a tab's width depends on the column it sits at, so a tab that survives the
	 * strip would be re-measured against the wrong column afterwards and silently lose
	 * width.
	 */
	public static function strip(string $line, int $columns): string
	{
		if ($columns <= 0) {
			return $line;
		}

		$width = self::width($line);
		return str_repeat(' ', max(0, $width - $columns)) . substr($line, strspn($line, " \t"));
	}
}
