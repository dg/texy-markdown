<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Blocks;

use Texy\Markdown\Internal\SyntaxRegistrar;
use Texy\Markdown\Registry;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Range;
use function strlen;


/**
 * `***`, `---` or `___` on a line of its own.
 *
 * @internal
 */
final class ThematicBreak implements SyntaxRegistrar
{
	public function __construct(
		private Registry $registry,
	) {
	}


	public function register(): void
	{
		$this->registry->addBlock(
			Syntax::ThematicBreak,
			'~^[ ]{0,3}([-*_])(?:[ \t]*\1){2,}[ \t]*$~',
			$this->parse(...),
		);
	}


	/**
	 * @param  array{string, string}  $m
	 * @param  array{int, int}  $offsets
	 */
	private function parse(ParseContext $ctx, array $m, array $offsets): Nodes\BlockNode
	{
		return new Nodes\HorizontalRuleNode($m[1], null, new Range($offsets[0], strlen($m[0])));
	}
}
