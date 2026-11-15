<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Passes;

use Texy\Node;
use Texy\Nodes;


/**
 * Turns every soft line break into a hard one, as Config::$mergeLines = false asks.
 *
 * Runs after the image pass, so the alternative text of an image, already plain text, is out of reach.
 *
 * @internal
 */
final class HardBreakPass
{
	public function process(Nodes\DocumentNode $document): void
	{
		$this->walk($document);
	}


	private function walk(Node $node): void
	{
		if ($node instanceof Nodes\ContentNode) {
			foreach ($node->children as $i => $child) {
				if ($child instanceof Nodes\SoftBreakNode) {
					$node->children[$i] = new Nodes\LineBreakNode($child->range);
				}
			}
		}

		foreach ($node->getChildren() as $child) {
			$this->walk($child);
		}
	}
}
