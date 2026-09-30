<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Modules;

use Texy;
use Texy\Markdown\Parsing\Indentation;
use Texy\Markdown\Parsing\Lines;
use Texy\Markdown\Parsing\LineState;
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
		$state = new LineState;
		$end = $offsets[0];

		while (($line = Lines::next($parser, $lineOffset)) !== null) {
			if (preg_match('~^[ ]{0,3}>~', $line, $marker)) {
				$content = self::stripMarker($line, strlen($marker[0]));
				// a tab partly expanded to spaces makes the content longer than its source
				$offset = max($lineOffset + strlen($marker[0]), $lineOffset + strlen($line) - strlen($content));
				$state->feed($content);
			} elseif ($state->inParagraph() && trim($line) !== '' && !Patterns::startsBlock($line)) {
				// lazy continuation; the marker stands on the newline before the line
				$content = Patterns::LazyMarker . $line;
				$offset = $lineOffset - 1;
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


	/**
	 * Drops the `>` and the one space that may follow it.
	 *
	 * When that space is part of a tab, only one column of the tab belongs to the
	 * marker; the rest is content and has to survive as spaces, because its width
	 * depends on a column the content alone no longer knows about.
	 */
	private static function stripMarker(string $line, int $markerLength): string
	{
		$rest = substr($line, $markerLength);
		$spaces = strspn($rest, " \t");
		return $spaces === 0
			? $rest
			: str_repeat(' ', Indentation::width($rest, $markerLength) - 1) . substr($rest, $spaces);
	}
}
