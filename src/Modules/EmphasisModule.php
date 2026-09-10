<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Modules;

use Texy;
use Texy\Markdown\Parsing\DelimiterNode;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\Engine;
use Texy\Parsing\ParseContext;
use Texy\Range;
use function strlen;


/**
 * Marks runs of `*` and `_`; the pairing is EmphasisPass's job.
 *
 * @internal
 */
final class EmphasisModule extends Texy\Module
{
	public function register(Engine $engine): void
	{
		$engine->addInline(
			Syntax::Delimiter,
			'~(?:(?<=(.))|(?<!.))(\*+|_+)(?=(.)|$)~s',
			$this->parseDelimiter(...),
		);
	}


	/**
	 * @param  array{string, ?string, string, ?string}  $m
	 * @param  array{int, ?int, int, ?int}  $offsets
	 */
	private function parseDelimiter(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		return new DelimiterNode(
			$m[2],
			$m[1] ?? '',
			$m[3] ?? '',
			new Range($offsets[2], strlen($m[2])),
		);
	}
}
