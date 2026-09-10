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


/**
 * Backslash escapes.
 *
 * @internal
 */
final class EscapeModule extends Texy\Module
{
	public function register(Registry $registry): void
	{
		$registry->addInline(Syntax::Escape, '~\\\(' . Patterns::Escapable . ')~', $this->parseEscape(...));
	}


	/**
	 * @param  array{string, string}  $m
	 * @param  array{int, int}  $offsets
	 */
	private function parseEscape(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		return new Nodes\TextNode($m[1], new Range($offsets[0], 2));
	}
}
