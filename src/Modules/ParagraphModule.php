<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Modules;

use Texy;
use Texy\Markdown\Parsing\DeferredNode;
use Texy\Markdown\Parsing\Indentation;
use Texy\Markdown\Parsing\Paragraphs;
use Texy\Markdown\Parsing\Patterns;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\Engine;
use Texy\Parsing\OffsetMap;
use Texy\Parsing\ParseContext;
use Texy\Range;
use function strlen;


/**
 * Everything the block patterns did not claim: indented code blocks and paragraphs, read by
 * a line state machine. The inline content is left to InlineParsePass as a DeferredNode.
 *
 * @internal
 */
final class ParagraphModule extends Texy\Module
{
	private const StateNone = 0;
	private const StateParagraph = 1;
	private const StateCode = 2;


	public function register(Engine $engine): void
	{
		$engine->setGapHandler($this->parse(...));
	}


	/**
	 * @return list<Nodes\BlockNode>
	 */
	private function parse(ParseContext $ctx, string $text, int $offset): array
	{
		$res = [];
		$state = self::StateNone;
		// the engine filters patterns by the enabled map, but this is not a pattern
		$indentedCode = !empty($ctx->allowedSyntaxes[Syntax::IndentedCode]);

		$paragraph = null;
		$paragraphNode = null;

		// A block handler that looked at the paragraph in front of its match and then
		// refused it hands the text back as a fresh gap that really is the continuation
		// of that paragraph. Continue it instead of starting a second one.
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
			// a container marked this line as its paragraph's lazy continuation; the
			// marker has done its job of keeping block patterns off it
			$line = ltrim($line, Patterns::LazyMarker);
			$blank = trim($line) === '';

			if ($state === self::StateCode) {
				if ($blank) {
					$pendingBlanks[] = Indentation::stripAt($line, $lineOffset, Indentation::TabStop);
				} elseif (Indentation::width($line) >= Indentation::TabStop) {
					array_push($codeLines, ...$pendingBlanks);
					$codeLines[] = Indentation::stripAt($line, $lineOffset, Indentation::TabStop);
					$pendingBlanks = [];
				} else {
					$res[] = self::createCodeBlock($codeLines, $codeStart, $lineOffset - 1);
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
					$codeLines = [Indentation::stripAt($line, $lineOffset, Indentation::TabStop)];
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
			$res[] = self::createCodeBlock($codeLines, $codeStart, $offset + $length);
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


	/** @param  list<array{content: string, offset: int}>  $lines */
	private static function createCodeBlock(array $lines, int $start, int $end): Nodes\CodeBlockNode
	{
		return new Nodes\CodeBlockNode(
			implode("\n", array_column($lines, 'content')),
			range: new Range($start, max(0, $end - $start)),
			codeMap: OffsetMap::fromLines($lines),
		);
	}
}
