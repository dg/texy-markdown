<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Modules;

use Texy;
use Texy\Markdown\Parsing\DeferredNode;
use Texy\Markdown\Parsing\Patterns;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Parsing\Registry;
use Texy\Range;
use function strlen;


/**
 * `### heading` and a paragraph underlined with `===` or `---`.
 *
 * The module goes after the list and the thematic break on purpose: all of them can match
 * at the same offset and the block parser then decides by registration order.
 *
 * @internal
 */
final class HeadingModule extends Texy\Module
{
	public function register(Registry $registry): void
	{
		$registry->addBlock(
			Syntax::HeadingAtx,
			'~^[ ]{0,3}(\#{1,6})(?:[ \t]++|$)([^\n]*+)$~',
			$this->parseAtx(...),
		);

		// The run of lines ends at the first underline, so it never gives a line back and the
		// repetition can be possessive: a paragraph of thousands of lines exhausts the stack of
		// PCRE otherwise. A run no underline follows is skipped whole, as none of its lines
		// starts a heading either.
		$excluded = '(?!' . Patterns::SetextExcluded . ')';
		$notUnderline = '(?![ ]{0,3}(?:=++|-++)[ \t]*+$)';
		$registry->addBlock(
			Syntax::HeadingSetext,
			'~^(' . $excluded . '[ ]{0,3}\S[^\n]*+\n(?:' . $excluded . $notUnderline . '[ \t]*+\S[^\n]*+\n)*+)'
			. '(?:[ ]{0,3}(=++|-++)[ \t]*+$|(*SKIP)(*FAIL))~',
			$this->parseSetext(...),
		);
	}


	/**
	 * @param  array{string, string, string}  $m
	 * @param  array{int, int, int}  $offsets
	 */
	private function parseAtx(ParseContext $ctx, array $m, array $offsets): Nodes\BlockNode
	{
		// the closing sequence of hashes is cut off by hand, a lazy pattern costs the square of the line
		$content = rtrim($m[2], " \t");
		$open = rtrim($content, '#');
		$bare = rtrim($open, " \t");
		if ($open === '' || $bare !== $open) {
			$content = $bare;
		}

		$contentOffset = $content === '' ? $offsets[0] : $offsets[2];
		return new Nodes\HeadingNode(
			$ctx->parseInline($content, $contentOffset),
			strlen($m[1]),
			Nodes\HeadingType::Surrounded,
			null,
			new Range($offsets[0], strlen($m[0])),
			$m[1],
			new Range($contentOffset, strlen($content)),
		);
	}


	/**
	 * @param  array{string, string, string}  $m
	 * @param  array{int, int, int}  $offsets
	 */
	private function parseSetext(ParseContext $ctx, array $m, array $offsets): Nodes\BlockNode
	{
		$deferred = null;
		$offset = $offsets[1];
		foreach (explode("\n", rtrim($m[1], "\n")) as $line) {
			$start = $offset + strspn($line, " \t");
			if ($deferred === null) {
				$deferred = new DeferredNode(trim($line), $start);
			} else {
				$deferred->append(trim($line), $start);
			}

			$offset += strlen($line) + 1;
		}

		$textOffset = $offsets[1] + strspn($m[1], " \t");
		return new Nodes\HeadingNode(
			new Nodes\ContentNode([$deferred]),
			$m[2][0] === '=' ? 1 : 2,
			Nodes\HeadingType::Underlined,
			null,
			new Range($offsets[0], strlen($m[0])),
			$m[2][0],
			new Range($textOffset, $offsets[1] + strlen(rtrim($m[1])) - $textOffset),
		);
	}
}
