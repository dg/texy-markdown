<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Blocks;

use Texy\Markdown\Internal\Lines;
use Texy\Markdown\Internal\Patterns;
use Texy\Markdown\Internal\SyntaxRegistrar;
use Texy\Markdown\Nodes\HtmlBlockNode;
use Texy\Markdown\Registry;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Range;
use function strlen;


/**
 * The seven kinds of HTML block. Types 1-5 have their own terminator, 6 and 7 end
 * at a blank line.
 *
 * @internal
 */
final class HtmlBlocks implements SyntaxRegistrar
{
	public function __construct(
		private Registry $registry,
	) {
	}


	public function register(): void
	{
		$this->registry->addBlock(
			Syntax::HtmlBlock,
			'~^[ ]{0,3}(?:'
			. '(?<t1><(?i:script|pre|style|textarea)(?=[ \t\n>]|$))'
			. '|(?<t2><!--)'
			. '|(?<t3><\?)'
			. '|(?<t4><![A-Za-z])'
			. '|(?<t5><!\[CDATA\[)'
			. '|(?<t6></?(?i:' . Patterns::HtmlBlockTags . ')(?=[ \t\n>]|/>|$))'
			. '|(?<t7>(?:' . Patterns::OpenTag . '|' . Patterns::CloseTag . ')[ \t]*$)'
			. ')~',
			$this->parse(...),
		);
	}


	/**
	 * @param  array{0: string, t1: ?string, t2: ?string, t3: ?string, t4: ?string, t5: ?string, t6: ?string, t7: ?string, ...}  $m
	 * @param  array{0: int, ...}  $offsets
	 */
	private function parse(ParseContext $ctx, array $m, array $offsets): Nodes\BlockNode
	{
		$parser = $ctx->getBlockParser();
		$parser->moveBackward();

		$end = match (true) {
			($m['t1'] ?? '') !== '' => '~</(?i:script|pre|style|textarea)>~',
			($m['t2'] ?? '') !== '' => '~-->~',
			($m['t3'] ?? '') !== '' => '~\?>~',
			($m['t4'] ?? '') !== '' => '~>~',
			($m['t5'] ?? '') !== '' => '~\]\]>~',
			default => null,
		};

		$lines = [];
		while (($line = Lines::next($parser)) !== null) {
			if ($end === null && trim($line) === '') {
				break;
			}

			$lines[] = $line;
			if ($end !== null && preg_match($end, $line)) {
				break;
			}
		}

		return new HtmlBlockNode(rtrim(implode("\n", $lines), "\n"), new Range($offsets[0], strlen($m[0])));
	}
}
