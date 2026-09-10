<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Parsing;

use Texy\Nodes;
use Texy\Parsing\ParseContext;


/**
 * Asking about the block in front of the current match.
 *
 * "This construct may not interrupt a paragraph" is a question about structure, not
 * about the preceding bytes: a lookbehind for a blank line silently gets it wrong
 * whenever another block sits between the paragraph and the match. The gap handler
 * runs before the match handler, so the paragraph is already in the node list.
 *
 * @internal
 */
final class Paragraphs
{
	public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . self::class);
	}


	/** The paragraph ending directly in front of $offset, if there is one. */
	public static function precedingAt(ParseContext $context, int $offset): ?Nodes\ParagraphNode
	{
		$nodes = $context->getBlockParser()->getNodes();
		$previous = end($nodes) ?: null;
		return $previous instanceof Nodes\ParagraphNode
			&& $previous->range !== null
			&& $previous->range->offset + $previous->range->length + 1 === $offset
			? $previous
			: null;
	}


	/** The deferred text of a paragraph the inline phase has not reached yet. */
	public static function deferredOf(Nodes\ParagraphNode $paragraph): ?DeferredNode
	{
		$first = $paragraph->content->children[0] ?? null;
		return $first instanceof DeferredNode ? $first : null;
	}
}
