<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Inlines;

use Texy\Markdown\Internal\Patterns;
use Texy\Markdown\Internal\SyntaxRegistrar;
use Texy\Markdown\Nodes\RawInlineNode;
use Texy\Markdown\Registry;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Range;
use function strlen;


/**
 * Raw HTML tags, comments, processing instructions, declarations and CDATA.
 *
 * Unlike an HTML block start condition, an inline tag may span lines, so this uses
 * the multi-line Attr variant.
 *
 * @internal
 */
final class RawHtml implements SyntaxRegistrar
{
	public function __construct(
		private Registry $registry,
	) {
	}


	public function register(): void
	{
		$this->registry->addLine(
			Syntax::HtmlInline,
			'~' . Patterns::OpenTag . '|' . Patterns::CloseTag . '|<!-->|<!--->|<!--(?:[^-]|-(?!->))*-->'
			. '|<\?[\s\S]*?\?>|<![A-Za-z][^>]*>|<!\[CDATA\[[\s\S]*?\]\]>~',
			$this->parse(...),
		);
	}


	/**
	 * @param  array{string}  $m
	 * @param  array{int}  $offsets
	 */
	private function parse(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		return new RawInlineNode($m[0], new Range($offsets[0], strlen($m[0])));
	}
}
