<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Passes;

use Texy\Markdown\Nodes\DeferredNode;
use Texy\Node;
use Texy\Nodes;
use Texy\Parsing\ParseContext;


/**
 * Runs the inline phase.
 *
 * The block phase leaves every piece of inline content as a DeferredNode; only here,
 * with every link reference definition collected, is it safe to parse. The children
 * are rewritten in place rather than through NodeTraverser, because one DeferredNode
 * expands to a sequence.
 *
 * @internal
 */
final class InlineParsePass
{
	public function __construct(
		private ParseContext $context,
	) {
	}


	public function process(Nodes\DocumentNode $document): void
	{
		$this->walk($document);
	}


	private function walk(Node $node): void
	{
		if ($node instanceof Nodes\ContentNode) {
			$res = [];
			foreach ($node->children as $child) {
				if ($child instanceof DeferredNode) {
					// trailing whitespace closes no construct: a hard break needs a line after it
					$parsed = $this->context->parseInline(rtrim($child->text));
					$child->map->applyTo($parsed);
					array_push($res, ...$parsed->children);
				} else {
					$res[] = $child;
				}
			}

			$node->children = $res;
		}

		foreach ($node->getChildren() as $child) {
			$this->walk($child);
		}
	}
}
