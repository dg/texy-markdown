<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Modules;

use Texy;
use Texy\Markdown\Parsing\DeferredNode;
use Texy\Markdown\Parsing\Lines;
use Texy\Markdown\Parsing\Patterns;
use Texy\Markdown\Syntax;
use Texy\Modifier;
use Texy\Nodes;
use Texy\Parsing\OffsetMap;
use Texy\Parsing\ParseContext;
use Texy\Parsing\Registry;
use Texy\Range;
use function count, strlen;


/**
 * GFM tables: a header row, a delimiter row that also carries the alignment, and any
 * number of body rows.
 *
 * The pattern matches the header together with the delimiter row; the handler then
 * reads the body rows that follow.
 *
 * @internal
 */
final class TableModule extends Texy\Module
{
	public function register(Registry $registry): void
	{
		// the match spans the header and the delimiter row: it is the delimiter row that
		// makes the line above it a header rather than a paragraph, so neither line can
		// be recognized without the other
		$registry->addBlock(
			Syntax::Table,
			'~^(?!' . Patterns::BlockStarts . ')(?!' . Patterns::LazyMarker . ')([ ]{0,3}[^\n]*\|[^\n]*)\n'
			. '[ ]{0,3}\|?[ \t]*:?-+:?[ \t]*(?:\|[ \t]*+:?-++:?[ \t]*+)*+\|?[ \t]*$~',
			$this->parse(...),
		);
	}


	/**
	 * @param  array{string, string}  $m
	 * @param  array{int, int}  $offsets
	 */
	private function parse(ParseContext $ctx, array $m, array $offsets): ?Nodes\BlockNode
	{
		$header = $m[1];
		$delimiters = self::splitRow(substr($m[0], strlen($header) + 1), 0);
		$aligns = array_map(fn($cell) => self::alignOf($cell[0]), $delimiters);
		$headerCells = self::splitRow($header, $offsets[0]);
		if (count($headerCells) !== count($aligns)) {
			// The one condition that cannot live in the pattern: a regexp cannot compare
			// the cell counts of two rows. Refusing is safe here only because the header
			// line is never a block start - the pattern says so - which makes it a setext
			// run, and setext is registered first and wins the offset outright.
			return null;
		}

		$parser = $ctx->getBlockParser();
		$rows = [self::createRow($headerCells, $aligns, new Range($offsets[0], strlen($header)), header: true)];
		$end = $offsets[0] + strlen($m[0]);

		while (($line = Lines::next($parser, $lineOffset)) !== null) {
			// a body row need not carry a pipe at all; only a blank line or another
			// block construct ends the table
			if (trim($line) === '' || Patterns::startsBlock($line)) {
				$parser->moveBackward();
				break;
			}

			$end = $lineOffset + strlen($line);
			$cells = array_slice(self::splitRow($line, $lineOffset), 0, count($aligns));
			$cells = array_pad($cells, count($aligns), ['', new Range($end, 0)]);
			$rows[] = self::createRow($cells, $aligns, new Range($lineOffset, strlen($line)), header: false);
		}

		return new Nodes\TableNode($rows, null, new Range($offsets[0], $end - $offsets[0]));
	}


	/**
	 * Splits a row on its unescaped pipes, dropping the optional leading and trailing one.
	 * @return list<array{string, Range}>  cell and its notation in the source
	 */
	private static function splitRow(string $line, int $offset): array
	{
		$parts = preg_split('~(?<!\\\\)\|~', $line, flags: PREG_SPLIT_OFFSET_CAPTURE);
		if (str_starts_with(ltrim($line), '|')) {
			array_shift($parts);
		}

		if (count($parts) > 1 && trim(end($parts)[0]) === '') {
			array_pop($parts);
		}

		return array_map(
			fn($part) => [trim($part[0]), new Range($offset + $part[1] + strspn($part[0], " \t"), strlen(trim($part[0])))],
			$parts,
		);
	}


	/**
	 * The escaped pipes of a cell become plain ones before inline parsing, so that they
	 * stay pipes even inside a code span; the map keeps the text after each of them in place.
	 */
	private static function unescapePipes(string $cell, int $offset): Nodes\ContentNode
	{
		$parts = explode('\|', $cell);
		$text = array_shift($parts);
		$starts = [0 => $offset];
		$offset += strlen($text);
		foreach ($parts as $part) {
			$text .= '|';
			$offset += 2;
			$starts[strlen($text)] = $offset;
			$text .= $part;
			$offset += strlen($part);
		}

		return new Nodes\ContentNode([DeferredNode::fromMap($text, new OffsetMap($starts))]);
	}


	private static function alignOf(string $spec): ?string
	{
		return match (true) {
			str_starts_with($spec, ':') && str_ends_with($spec, ':') => 'center',
			str_ends_with($spec, ':') => 'right',
			str_starts_with($spec, ':') => 'left',
			default => null,
		};
	}


	/**
	 * @param  list<array{string, Range}>  $cells
	 * @param  list<?string>  $aligns
	 */
	private static function createRow(array $cells, array $aligns, Range $range, bool $header): Nodes\TableRowNode
	{
		$nodes = [];
		foreach ($cells as $i => [$cell, $cellRange]) {
			$modifier = null;
			if (($aligns[$i] ?? null) !== null) {
				$modifier = new Modifier;
				$modifier->hAlign = $aligns[$i];
			}

			$nodes[] = new Nodes\TableCellNode(
				self::unescapePipes($cell, $cellRange->offset),
				1,
				1,
				$header,
				$modifier,
				$cellRange,
			);
		}

		return new Nodes\TableRowNode($nodes, $header, null, $range);
	}
}
