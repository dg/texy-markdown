<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Modules;

use Texy;
use Texy\Markdown\Parsing\Lines;
use Texy\Markdown\Parsing\Patterns;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\OffsetMap;
use Texy\Parsing\ParseContext;
use Texy\Parsing\Registry;
use Texy\Range;
use function strlen;


/**
 * `> quote`: strips one level of the marker and hands the rest back to the block parser.
 *
 * @internal
 */
final class BlockQuoteModule extends Texy\Module
{
	public function register(Registry $registry): void
	{
		$registry->addBlock(Syntax::BlockQuote, '~^[ ]{0,3}>~', $this->parse(...));
	}


	/**
	 * @param  array{string}  $m
	 * @param  array{int}  $offsets
	 */
	private function parse(ParseContext $ctx, array $m, array $offsets): Nodes\BlockNode
	{
		$parser = $ctx->getBlockParser();
		$parser->moveBackward();
		$lines = [];
		$inParagraph = false;
		$end = $offsets[0];

		while (($line = Lines::next($parser, $lineOffset)) !== null) {
			if (preg_match('~^[ ]{0,3}>[ ]?~', $line, $marker)) {
				$content = substr($line, strlen($marker[0]));
				$offset = $lineOffset + strlen($marker[0]);
				$inParagraph = trim($content) !== '';
			} elseif ($inParagraph && trim($line) !== '' && !Patterns::startsBlock($line)) {
				$content = $line; // lazy continuation
				$offset = $lineOffset;
			} else {
				$parser->moveBackward();
				break;
			}

			$lines[] = ['content' => $content, 'offset' => $offset];
			$end = $lineOffset + strlen($line);
		}

		$content = $ctx->parseBlock(implode("\n", array_column($lines, 'content')));
		OffsetMap::fromLines($lines)->applyTo($content);
		return new Nodes\BlockQuoteNode($content, null, new Range($offsets[0], $end - $offsets[0]));
	}
}
