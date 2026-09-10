<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Inlines;

use Texy\Markdown\Internal\Patterns;
use Texy\Markdown\Internal\SyntaxRegistrar;
use Texy\Markdown\Nodes\SoftBreakNode;
use Texy\Markdown\Registry;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Range;
use function strlen;


/**
 * Backslash escapes and line breaks.
 *
 * @internal
 */
final class Escapes implements SyntaxRegistrar
{
	public function __construct(
		private Registry $registry,
	) {
	}


	public function register(): void
	{
		$this->registry->addLine(Syntax::Escape, '~\\\(' . Patterns::Escapable . ')~', $this->parseEscape(...));

		// a backslash or two spaces before a newline is a hard break; a bare newline is a soft one
		$this->registry->addLine(Syntax::EscapedBreak, '~\\\\\n~', $this->parseEscapedBreak(...));
		$this->registry->addLine(Syntax::HardBreak, '~ {2,}\n~', $this->parseHardBreak(...));
		$this->registry->addLine(Syntax::SoftBreak, '~[ \t]*\n[ \t]*~', $this->parseSoftBreak(...));
	}


	/**
	 * @param  array{string, string}  $m
	 * @param  array{int, int}  $offsets
	 */
	private function parseEscape(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		return new Nodes\TextNode($m[1], new Range($offsets[0], 2));
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
		return new SoftBreakNode(new Range($offsets[0], strlen($m[0])));
	}
}
