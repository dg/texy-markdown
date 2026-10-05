<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Passes;

use Texy\Markdown\Nodes\DeferredNode;
use Texy\Node;
use Texy\Nodes;
use Texy\Range;
use function strlen;


/**
 * GFM task list items: `- [ ]` and `- [x]`.
 *
 * The marker becomes a disabled checkbox in front of the item's content, which is
 * exactly what the extension produces - there is no node type to invent here, and a
 * flag on the item would have to be rendered as that same input element anyway.
 *
 * Runs before InlineParsePass, while the item's content is still deferred text.
 *
 * @internal
 */
final class TaskListPass
{
	public function __construct(
		private bool $enabled,
	) {
	}


	public function process(Nodes\DocumentNode $document): void
	{
		if ($this->enabled) {
			$this->walk($document);
		}
	}


	private function walk(Node $node): void
	{
		if ($node instanceof Nodes\ListItemNode) {
			self::convert($node);
		}

		foreach ($node->getChildren() as $child) {
			$this->walk($child);
		}
	}


	private static function convert(Nodes\ListItemNode $item): void
	{
		$paragraph = $item->content->children[0] ?? null;
		if (!$paragraph instanceof Nodes\ParagraphNode) {
			return;
		}

		$deferred = $paragraph->content->children[0] ?? null;
		// the whitespace after the marker belongs to the content, not to the marker
		if (!$deferred instanceof DeferredNode
			|| !preg_match('~^\[([ xX])\](?=[ \t])~', $deferred->text, $m)
		) {
			return;
		}

		$start = $deferred->map->toAbsolute(0);
		$marker = new Range($start, $deferred->map->toAbsolute(strlen($m[0])) - $start);
		$deferred->cut(strlen($m[0]));

		$attributes = ['disabled' => '', 'type' => 'checkbox'];
		if ($m[1] !== ' ') {
			$attributes = ['checked' => ''] + $attributes;
		}

		array_unshift($paragraph->content->children, new Nodes\HtmlTagNode('input', $attributes, range: $marker));
	}
}
