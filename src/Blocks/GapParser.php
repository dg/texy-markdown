<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Blocks;

use Texy\Markdown\Internal\Indentation;
use Texy\Markdown\Internal\Paragraphs;
use Texy\Markdown\Internal\SyntaxRegistrar;
use Texy\Markdown\Nodes\DeferredNode;
use Texy\Markdown\ParseState;
use Texy\Markdown\Registry;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Range;
use function strlen;


/**
 * Everything the block patterns did not claim: indented code blocks and paragraphs.
 *
 * The two belong together because they are exactly the constructs that cannot
 * interrupt a paragraph, and "cannot interrupt a paragraph" is a *state* here, not
 * something a lookbehind can approximate. So this is a small line state machine
 * (none / paragraph / code) rather than a split on blank lines - an indented code
 * block contains blank lines of its own.
 *
 * The inline content is not parsed here; it is handed to InlineParsePass as a
 * DeferredNode, because a link reference definition may still be waiting further down
 * the document.
 *
 * @internal
 */
final class GapParser implements SyntaxRegistrar
{
	private const StateNone = 0;
	private const StateParagraph = 1;
	private const StateCode = 2;


	public function __construct(
		private Registry $registry,
		private ParseState $parseState,
	) {
	}


	public function register(): void
	{
		$this->registry->setGapHandler($this->parse(...));
	}


	/**
	 * @return list<Nodes\BlockNode>
	 */
	private function parse(ParseContext $ctx, string $text, int $offset): array
	{
		$res = [];
		$state = self::StateNone;
		// the engine filters patterns by the enabled map, but this is not a pattern
		$indentedCode = $this->parseState->isAllowed(Syntax::CodeIndented);

		$paragraph = null;
		$paragraphNode = null;

		// A block handler that refuses its match hands the text back, and it arrives
		// here as a fresh gap that really is the continuation of the paragraph just
		// emitted. Continue that paragraph instead of starting a second one.
		$previous = $this->adjacentParagraph($ctx, $offset);
		if ($previous !== null) {
			[$paragraphNode, $paragraph] = $previous;
			$state = self::StateParagraph;
		}

		$codeLines = $pendingBlanks = [];
		$codeStart = 0;
		$pos = 0;
		$length = strlen($text);

		while ($pos <= $length) {
			$eol = strpos($text, "\n", $pos);
			$line = $eol === false ? substr($text, $pos) : substr($text, $pos, $eol - $pos);
			$lineOffset = $offset + $pos;
			$lineEnd = $lineOffset + strlen($line);
			$blank = trim($line) === '';

			if ($state === self::StateCode) {
				if ($blank) {
					$pendingBlanks[] = Indentation::strip($line, Indentation::TabStop);
				} elseif (Indentation::width($line) >= Indentation::TabStop) {
					$codeLines = [...$codeLines, ...$pendingBlanks, Indentation::strip($line, Indentation::TabStop)];
					$pendingBlanks = [];
				} else {
					$res[] = self::code($codeLines, $codeStart, $lineOffset - 1);
					$codeLines = $pendingBlanks = [];
					$state = self::StateNone;
					continue; // the line itself has not been consumed yet
				}
			} elseif ($state === self::StateParagraph) {
				if ($blank) {
					$paragraph = $paragraphNode = null;
					$state = self::StateNone;
				} else {
					$content = ltrim($line);
					$paragraph->append($content, $lineEnd - strlen($content));
					$start = $paragraphNode->range->offset ?? $lineOffset;
					$paragraphNode->range = new Range($start, $lineEnd - $start);
				}
			} elseif (!$blank) {
				// state none: indented code, otherwise a paragraph
				if ($indentedCode && Indentation::width($line) >= Indentation::TabStop) {
					$state = self::StateCode;
					$codeStart = $lineOffset;
					$codeLines = [Indentation::strip($line, Indentation::TabStop)];
				} else {
					$state = self::StateParagraph;
					$content = ltrim($line);
					$paragraph = new DeferredNode($content, $lineEnd - strlen($content));
					$paragraphNode = new Nodes\ParagraphNode(
						new Nodes\ContentNode([$paragraph]),
						null,
						new Range($lineOffset, $lineEnd - $lineOffset),
					);
					$res[] = $paragraphNode;
				}
			}

			if ($eol === false) {
				break;
			}

			$pos = $eol + 1;
		}

		if ($state === self::StateCode) {
			$res[] = self::code($codeLines, $codeStart, $offset + $length);
		}

		return $res;
	}


	/**
	 * The paragraph this gap continues, if the block directly before it is one.
	 * @return ?array{Nodes\ParagraphNode, DeferredNode}
	 */
	private function adjacentParagraph(ParseContext $ctx, int $offset): ?array
	{
		$previous = Paragraphs::precedingAt($ctx, $offset);
		$deferred = $previous === null ? null : Paragraphs::deferredOf($previous);
		return $previous !== null && $deferred !== null ? [$previous, $deferred] : null;
	}


	/** @param  list<string>  $lines */
	private static function code(array $lines, int $start, int $end): Nodes\CodeBlockNode
	{
		return new Nodes\CodeBlockNode(
			'code',
			implode("\n", $lines) . "\n",
			null,
			null,
			new Range($start, max(0, $end - $start)),
		);
	}
}
