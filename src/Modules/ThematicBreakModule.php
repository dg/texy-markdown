<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Modules;

use Texy;
use Texy\Markdown\Parsing\Patterns;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Parsing\Registry;
use Texy\Range;
use function strlen;


/**
 * `***`, `---` or `___` on a line of its own.
 *
 * @internal
 */
final class ThematicBreakModule extends Texy\Module
{
	public function register(Registry $registry): void
	{
		$registry->addBlock(
			Syntax::ThematicBreak,
			'~^' . Patterns::ThematicBreak . '~',
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
