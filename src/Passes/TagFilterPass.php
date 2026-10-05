<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Passes;

use Texy\Helpers;
use Texy\Node;
use Texy\Nodes;


/**
 * GFM's disallowed raw HTML: a fixed list of tags becomes text, like a tag the HTML policy refuses.
 *
 * This is what the extension does and no more - it is a rule of the GFM profile, not
 * sanitization and not a substitute for it; see Configurator::safeMode().
 *
 * @internal
 */
final class TagFilterPass
{
	private const Disallowed = [
		'title' => 1,
		'textarea' => 1,
		'style' => 1,
		'xmp' => 1,
		'iframe' => 1,
		'noembed' => 1,
		'noframes' => 1,
		'script' => 1,
		'plaintext' => 1,
	];


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
		if ($node instanceof Nodes\ContentNode) {
			foreach ($node->children as $i => $child) {
				if ($child instanceof Nodes\HtmlTagNode && isset(self::Disallowed[strtolower($child->name)])) {
					$node->children[$i] = new Nodes\RawTextNode(Helpers::decodeEntities($child->toSource()), $child->range);
				}
			}
		}

		foreach ($node->getChildren() as $child) {
			$this->walk($child);
		}
	}
}
