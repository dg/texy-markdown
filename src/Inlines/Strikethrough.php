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
 * GFM strikethrough: `~~text~~`, and a single tilde as well.
 *
 * A third delimiter character fed into the same machinery as `*` and `_`; only the
 * pairing rule differs, see EmphasisPass. Three or more tildes are a fenced code
 * block, which is a block construct and never reaches here.
 *
 * @internal
 */
final class Strikethrough implements SyntaxRegistrar
{
	public function __construct(
		private Registry $registry,
	) {
	}


	public function register(): void
	{
		$this->registry->addLine(
			Syntax::Strikethrough,
			'~(?:(?<=(.))|(?<!.))(\~{1,2})(?!\~)(?=(.)|$)~s',
			$this->parse(...),
		);
	}


	/**
	 * @param  array{string, ?string, string, ?string}  $m
	 * @param  array{int, ?int, int, ?int}  $offsets
	 */
	private function parse(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		return new DelimiterNode($m[2], $m[1] ?? '', $m[3] ?? '', new Range($offsets[2], strlen($m[2])));
	}
}
