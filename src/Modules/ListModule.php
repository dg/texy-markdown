<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Modules;

use Texy;
use Texy\Markdown\Parsing\Indentation;
use Texy\Markdown\Parsing\Lines;
use Texy\Markdown\Parsing\Paragraphs;
use Texy\Markdown\Parsing\Patterns;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\OffsetMap;
use Texy\Parsing\ParseContext;
use Texy\Parsing\Registry;
use Texy\Range;
use function strlen;


/**
 * Bullet and ordered lists: collects the items, strips each one's indentation and
 * parses its content as blocks.
 *
 * @internal
 */
final class ListModule extends Texy\Module
{
	public function register(Registry $registry): void
	{
		// the lookahead captures the rest of the line for the handler
		$registry->addBlock(
			Syntax::List,
			'~^([ ]{0,3})([-+*]|[0-9]{1,9}[.)])(?=([ \t][^\n]*)|$)~',
			$this->parse(...),
		);
	}


	/**
	 * @param  array{string, string, string, ?string}  $m
	 * @param  array{int, int, int, ?int}  $offsets
	 */
	private function parse(ParseContext $ctx, array $m, array $offsets): Nodes\BlockNode|Nodes\ContentNode|null
	{
		$parser = $ctx->getBlockParser();
		$markerType = self::markerType($m[2]);
		$start = ctype_digit($m[2][0]) ? (int) substr($m[2], 0, -1) : null;

		// only a list starting at 1 with a non-empty first item may interrupt a paragraph;
		// otherwise the line is grafted onto the preceding paragraph as its continuation
		$previous = Paragraphs::precedingAt($ctx, $offsets[0]);
		$deferred = $previous === null ? null : Paragraphs::deferredOf($previous);
		if ($previous?->range !== null
			&& $deferred !== null
			&& (($start !== null && $start !== 1) || trim($m[3] ?? '') === '')
		) {
			$parser->moveBackward();
			$line = Lines::next($parser, $lineOffset) ?? throw new \LogicException('The line just matched must be readable.');
			$end = $lineOffset + strlen($line);
			$deferred->append(trim($line), $lineOffset + strspn($line, " \t"));
			$previous->range = new Range($previous->range->offset, $end - $previous->range->offset);
			return new Nodes\ContentNode;
		}

		$parser->moveBackward();
		$items = $starts = $ends = [];
		$current = null;
		$contentIndent = 0;
		$blanks = 0;
		$loose = false;
		$end = $offsets[0];

		while (($l = Lines::next($parser, $lineOffset)) !== null) {
			if (trim($l) === '') {
				if ($current === null) {
					$parser->moveBackward();
					break;
				}

				$blanks++;
				$current[] = ['content' => '', 'offset' => $lineOffset + strlen($l)];
				continue;
			}

			if (preg_match('~^([ ]{0,3})([-+*]|[0-9]{1,9}[.)])([ \t]+|$)~', $l, $marker)
				&& self::markerType($marker[2]) === $markerType
				&& ($current === null || strlen($marker[1]) < $contentIndent)
			) {
				if ($current !== null) {
					$loose = $loose || $blanks > 0;
					$items[] = $current;
					$ends[] = $end;
				}

				$blanks = 0;
				$rest = substr($l, strlen($marker[0]));
				$width = strlen($marker[1]) + strlen($marker[2]);
				$spaces = strlen($marker[3]);

				// more than four spaces after the marker mean an indented code block
				$contentIndent = $spaces > Indentation::TabStop || ($spaces === 0 && trim($rest) === '')
					? $width + 1
					: $width + $spaces;
				if ($spaces > Indentation::TabStop) {
					$rest = str_repeat(' ', $spaces - 1) . $rest;
				}

				// spaces standing in for a tab or added for a code block make the content longer than its source
				$current = [['content' => $rest, 'offset' => max($lineOffset + $width, $lineOffset + strlen($l) - strlen($rest))]];
				$starts[] = $lineOffset + strlen($marker[1]);
				$end = $lineOffset + strlen($l);
				continue;
			}

			if ($current === null) {
				$parser->moveBackward();
				break;
			}

			if (Indentation::width($l) >= $contentIndent) {
				$content = Indentation::strip($l, $contentIndent);
				$current[] = ['content' => $content, 'offset' => max($lineOffset, $lineOffset + strlen($l) - strlen($content))];
				$blanks = 0;
				$end = $lineOffset + strlen($l);
				continue;
			}

			if ($blanks === 0 && !Patterns::startsBlock($l) && trim(end($current)['content']) !== '') {
				$content = ltrim($l); // lazy continuation
				$current[] = ['content' => $content, 'offset' => $lineOffset + strlen($l) - strlen($content)];
				$end = $lineOffset + strlen($l);
				continue;
			}

			$parser->moveBackward();
			break;
		}

		if ($current !== null) {
			$items[] = $current;
			$ends[] = $end;
		}

		if (!$items) {
			return null;
		}

		$itemNodes = [];
		foreach ($items as $i => $lines) {
			$text = rtrim(implode("\n", array_column($lines, 'content')), "\n");
			// a blank line makes the list loose only between the item's own blocks
			$stripped = preg_replace('~^([ ]{0,3})(`{3,}|\~{3,})[^\n]*\n(.*?\n)??\1?\2[ \t]*(?=\n|$)~ms', '', $text);
			$loose = $loose || (bool) preg_match('~\n[ \t]*\n(?![ \t])(?!$)~', $stripped);
			$content = $ctx->parseBlock($text);
			OffsetMap::fromLines($lines)->applyTo($content);
			$itemNodes[] = new Nodes\ListItemNode(
				$content,
				range: new Range($starts[$i], $ends[$i] - $starts[$i]),
			);
		}

		return new Nodes\ListNode(
			$itemNodes,
			str_starts_with($markerType, 'ol') ? Nodes\ListType::Decimal : Nodes\ListType::Unordered,
			$start === 1 ? null : $start,
			range: new Range($offsets[0], $end - $offsets[0]),
			paragraphs: $loose ? Nodes\ItemParagraphs::Wrapped : Nodes\ItemParagraphs::Unwrapped,
		);
	}


	private static function markerType(string $marker): string
	{
		return ctype_digit($marker[0]) ? 'ol' . substr($marker, -1) : 'ul' . $marker;
	}
}
