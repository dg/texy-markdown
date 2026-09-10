<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Inlines;

use Texy\Markdown\Internal\SyntaxRegistrar;
use Texy\Markdown\Nodes\DelimiterNode;
use Texy\Markdown\Registry;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Range;
use function strlen;


/**
 * Marks runs of `*` and `_`; the pairing is EmphasisPass's job.
 *
 * The neighbouring characters are captured through a lookbehind rather than consumed,
 * because a character between two runs is context for both of them.
 *
 * @internal
 */
final class Emphasis implements SyntaxRegistrar
{
	public function __construct(
		private Registry $registry,
	) {
	}


	public function register(): void
	{
		$this->registry->addLine(
			Syntax::Delimiter,
			'~(?:(?<=(.))|(?<!.))(\*+|_+)(?=(.)|$)~s',
			$this->parse(...),
		);
	}


	/**
	 * @param  array{string, ?string, string, ?string}  $m
	 * @param  array{int, ?int, int, ?int}  $offsets
	 */
	private function parse(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		return new DelimiterNode(
			$m[2],
			$m[1] ?? '',
			$m[3] ?? '',
			new Range($offsets[2], strlen($m[2])),
		);
	}
}
