<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Parsing;

use Texy\Nodes\ContentNode;
use Texy\Nodes\InlineNode;
use Texy\Parsing\OffsetMap;
use Texy\Range;
use function strlen;


/**
 * Inline content the block phase has read but deliberately not parsed yet.
 *
 * A link reference definition may sit anywhere in the document, including after the
 * paragraph that uses it, so no inline content can be resolved until the block phase
 * has finished. Every construct that carries inline content emits one of these
 * instead of calling parseInline(); InlineParsePass replaces it in place.
 *
 * The lines of the text are trimmed, so $map leads each of them back to the source.
 *
 * No DeferredNode survives the parse phase.
 *
 * @internal
 */
class DeferredNode extends InlineNode
{
	public OffsetMap $map;


	public function __construct(
		public string $text,
		int $offset,
	) {
		$this->map = new OffsetMap([0 => $offset]);
		$this->range = new Range($offset, strlen($text));
	}


	/** Text that differs from its source also inside a line, so the map has more entries than line starts. */
	public static function fromMap(string $text, OffsetMap $map): self
	{
		$start = $map->toAbsolute(0);
		$node = new self($text, $start);
		$node->map = $map;
		$node->range = new Range($start, $map->toAbsolute(strlen($text)) - $start);
		return $node;
	}


	/** The content slot of a block node is a ContentNode, so wrap the deferred text in one. */
	public static function wrap(string $text, int $offset): ContentNode
	{
		return new ContentNode([new self($text, $offset)]);
	}


	/** Appends a line that starts at $offset in the source. */
	public function append(string $line, int $offset): void
	{
		$start = $this->map->toAbsolute(0);
		$this->map->add(strlen($this->text) + 1, $offset);
		$this->text .= "\n" . $line;
		$this->range = new Range($start, $offset + strlen($line) - $start);
	}


	/** Removes the first $length bytes of the text. */
	public function cut(int $length): void
	{
		$starts = [0 => $this->map->toAbsolute($length)];
		foreach ($this->readLineStarts() as $local => $offset) {
			if ($local > $length) {
				$starts[$local - $length] = $offset;
			}
		}

		$end = $this->map->toAbsolute(strlen($this->text));
		$this->text = substr($this->text, $length);
		$this->map = new OffsetMap($starts);
		$this->range = new Range($starts[0], $end - $starts[0]);
	}


	/** @return array<int, int>  local offset of line start => offset in the source */
	private function readLineStarts(): array
	{
		$starts = [0 => $this->map->toAbsolute(0)];
		for ($pos = strpos($this->text, "\n"); $pos !== false; $pos = strpos($this->text, "\n", $pos + 1)) {
			$starts[$pos + 1] = $this->map->toAbsolute($pos + 1);
		}

		return $starts;
	}
}
