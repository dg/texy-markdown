<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Passes;

use Texy\Markdown\Parsing\DeferredNode;
use Texy\Node;
use Texy\Nodes;
use function strlen;


/**
 * GFM task list items: `- [ ]` and `- [x]`.
 *
 * The marker is taken from the text of the item and recorded on it.
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
		if (!$deferred instanceof DeferredNode
			|| !preg_match('~^\[([ xX])\][ \t]+~', $deferred->text, $m)
		) {
			return;
		}

		$deferred->cut(strlen($m[0]));
		$item->checked = $m[1] !== ' ';
	}
}
