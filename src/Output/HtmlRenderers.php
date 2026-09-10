<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Output;

use Texy\Markdown\Nodes;
use Texy\Nodes as TexyNodes;
use Texy\Output\Html\Element;
use Texy\Output\Html\Raw;
use Texy\Output\Html\Renderer;


/**
 * Teaches Texy's HTML renderer the four node types Markdown adds.
 *
 * Four, because everything else a Markdown document parses into is a core node with a
 * core renderer already - that is what reusing the Texy AST buys. The handlers are
 * registered on a renderer instance, never on a shared config: a caller's Texy is
 * configuration this package has no business mutating.
 *
 * @internal
 */
final class HtmlRenderers
{
	public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . self::class);
	}


	public static function install(Renderer $renderer): void
	{
		$renderer->addRenderer(fn(Nodes\HtmlBlockNode $node): Raw|string => $node->html === ''
			? ''
			: new Raw($node->html));

		$renderer->addRenderer(fn(Nodes\RawInlineNode $node): Raw => new Raw($node->html));

		$renderer->addRenderer(fn(Nodes\SoftBreakNode $node): string => "\n");

		$renderer->addRenderer(self::renderList(...));
	}


	/**
	 * Tight and loose lists differ only in whether the items keep their paragraphs, and
	 * that difference has to be rendered: it is the one thing the distinction means, and
	 * every stylesheet turns it into vertical spacing.
	 *
	 * The core list renderer cannot serve either case. It drops the wrapper of an item's
	 * first paragraph - reasonable for Texy, where a list item is a block container in
	 * its own right, but here it would make every loose list look tight - and it keeps
	 * the wrappers of the others, which makes a tight item with two blocks look loose.
	 */
	private static function renderList(Nodes\ListNode $node, Renderer $renderer): Element
	{
		$el = new Element($node->type->isOrdered() ? 'ol' : 'ul');
		$renderer->decorateElement($node->modifier, $el);
		if ($node->start !== null && $node->start > 1) {
			$el->attrs['start'] = $node->start;
		}

		foreach ($node->items as $item) {
			$li = new Element('li');
			$renderer->decorateElement($item->modifier, $li);
			foreach ($item->content->children as $block) {
				$children = $node->tight && $block instanceof TexyNodes\ParagraphNode
					? $block->content->children
					: [$block];
				array_push($li->children, ...$renderer->renderNodes($children));
			}

			$el->add($li);
		}

		return $el;
	}
}
