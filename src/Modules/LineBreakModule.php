<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Modules;

use Texy;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Parsing\Registry;
use Texy\Range;
use function strlen;


/**
 * Hard and soft line breaks.
 *
 * @internal
 */
final class LineBreakModule extends Texy\Module
{
	public function register(Registry $registry): void
	{
		// a backslash or two spaces before a newline is a hard break; a bare newline is a soft one
		$registry->addInline(Syntax::EscapedBreak, '~\\\\\n~', $this->parseEscapedBreak(...));
		$registry->addInline(Syntax::HardBreak, '~ {2,}\n~', $this->parseHardBreak(...));
		$registry->addInline(Syntax::SoftBreak, '~[ \t]*\n[ \t]*~', $this->parseSoftBreak(...));
	}


	/**
	 * @param  array{string}  $m
	 * @param  array{int}  $offsets
	 */
	private function parseEscapedBreak(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		return new Nodes\LineBreakNode(new Range($offsets[0], 2));
	}


	/**
	 * @param  array{string}  $m
	 * @param  array{int}  $offsets
	 */
	private function parseHardBreak(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		return new Nodes\LineBreakNode(new Range($offsets[0], strlen($m[0])));
	}


	/**
	 * @param  array{string}  $m
	 * @param  array{int}  $offsets
	 */
	private function parseSoftBreak(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		return new Nodes\SoftBreakNode(new Range($offsets[0], strlen($m[0])));
	}
}
