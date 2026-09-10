<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Inlines;

use Texy\Markdown\Internal\SyntaxRegistrar;
use Texy\Markdown\Registry;
use Texy\Markdown\Syntax as MdSyntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Range;
use Texy\Syntax;
use function strlen;


/**
 * `` `code` ``, with any number of backticks.
 *
 * @internal
 */
final class CodeSpans implements SyntaxRegistrar
{
	public function __construct(
		private Registry $registry,
	) {
	}


	public function register(): void
	{
		$this->registry->addLine(
			MdSyntax::CodeSpan,
			'~(?<!`)(`+)(?!`)([\s\S]*?)(?<!`)\1(?!`)~',
			$this->parse(...),
		);
	}


	/**
	 * @param  array{string, string, string}  $m
	 * @param  array{int, int, int}  $offsets
	 */
	private function parse(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		$code = str_replace("\n", ' ', $m[2]);
		if (str_starts_with($code, ' ') && str_ends_with($code, ' ') && trim($code) !== '') {
			$code = substr($code, 1, -1);
		}

		return new Nodes\PhraseNode(
			new Nodes\ContentNode([new Nodes\RawTextNode($code)]),
			Syntax::Code,
			null,
			new Range($offsets[0], strlen($m[0])),
		);
	}
}
