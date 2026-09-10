<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Parsing;

use Texy\Parsing\BlockParser;


/**
 * Reading raw lines from the block parser.
 *
 * Multi-line constructs all consume the input line by line, and BlockParser::next()
 * cannot express in its type that group 0 always participates in a successful match.
 * This wraps that one detail so the handlers stay readable.
 *
 * @internal
 */
final class Lines
{
	public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . self::class);
	}


	/**
	 * Returns the next line, or null at the end of the input.
	 * @param-out int $offset
	 */
	public static function next(BlockParser $parser, ?int &$offset = null): ?string
	{
		if (!$parser->next('~[^\n]*~', $m, $offsets)) {
			$offset = -1;
			return null;
		}

		$offset = $offsets[0] ?? throw new \LogicException('Match without group 0.');
		return $m[0] ?? throw new \LogicException('Match without group 0.');
	}
}
