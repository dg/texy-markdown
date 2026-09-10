<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Parsing;

use function strlen;


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
	 * Width of the line's leading whitespace in columns.
	 */
	public static function width(string $line): int
	{
		$column = 0;
		for ($i = 0; $i < strspn($line, " \t"); $i++) {
			$column += $line[$i] === "\t" ? self::TabStop - $column % self::TabStop : 1;
		}

		return $column;
	}


	/**
	 * Removes $columns columns of indentation. A tab straddling the boundary leaves
	 * the columns it covers behind as spaces.
	 */
	public static function strip(string $line, int $columns): string
	{
		$i = 0;
		$column = 0;
		while ($i < strlen($line) && $column < $columns) {
			if ($line[$i] === ' ') {
				$column++;
			} elseif ($line[$i] === "\t") {
				$column += self::TabStop - $column % self::TabStop;
			} else {
				break;
			}

			$i++;
		}

		return str_repeat(' ', max(0, $column - $columns)) . substr($line, $i);
	}


	/**
	 * Removes $columns columns of indentation from the line at $offset, as a line of an OffsetMap.
	 * Where a tab expanded to spaces makes it longer than its source, it starts at the line.
	 * @return array{content: string, offset: int}
	 */
	public static function stripAt(string $line, int $offset, int $columns): array
	{
		$content = self::strip($line, $columns);
		return ['content' => $content, 'offset' => $offset + max(0, strlen($line) - strlen($content))];
	}
}
