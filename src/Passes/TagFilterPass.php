<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Passes;

use Texy\Markdown\Nodes\HtmlBlockNode;
use Texy\Markdown\Nodes\RawInlineNode;
use Texy\Node;
use Texy\Nodes;


/**
 * GFM's disallowed raw HTML: a fixed list of tags is escaped instead of passed through.
 *
 * This is what the extension does and no more - it is not sanitization, and it is not
 * a substitute for it. To keep raw HTML out entirely, switch the html/block and
 * html/inline syntaxes off; see Configurator::safeMode().
 *
 * @internal
 */
final class TagFilterPass
{
	private const Disallowed = 'title|textarea|style|xmp|iframe|noembed|noframes|script|plaintext';


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
		if ($node instanceof HtmlBlockNode) {
			$node->html = self::filter($node->html);
		} elseif ($node instanceof RawInlineNode) {
			$node->html = self::filter($node->html);
		}

		foreach ($node->getChildren() as $child) {
			$this->walk($child);
		}
	}


	private static function filter(string $html): string
	{
		return preg_replace('~<(?=/?(?:' . self::Disallowed . ')(?=[ \t\n>/]|$))~i', '&lt;', $html);
	}
}
