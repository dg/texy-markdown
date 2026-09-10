<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Passes;

use Texy\Markdown\Nodes\DelimiterNode;
use Texy\Node;
use Texy\Nodes;
use Texy\Syntax;
use function count, is_int;


/**
 * The spec's "process emphasis" algorithm over the flat child list of one container.
 *
 * Whether a delimiter run opens emphasis, closes it or is literal text is a property
 * of the whole sequence of runs, which no syntax handler can see - hence a pass.
 * Every DelimiterNode that survives the pairing becomes literal text, so none of them
 * leaves the parse phase.
 *
 * @internal
 */
final class EmphasisPass
{
	public function process(Nodes\DocumentNode $document): void
	{
		$this->walk($document);
	}


	private function walk(Node $node): void
	{
		if ($node instanceof Nodes\ContentNode) {
			$this->processContainer($node);
		}

		foreach ($node->getChildren() as $child) {
			$this->walk($child);
		}
	}


	private function processContainer(Nodes\ContentNode $container): void
	{
		$nodes = $container->children;
		/** @var list<DelimiterNode> $delims */
		$delims = array_values(array_filter($nodes, fn($node) => $node instanceof DelimiterNode));
		$openersBottom = [];
		$pos = 0;

		while ($pos < count($delims)) {
			$closer = $delims[$pos];
			if (!$closer->canClose) {
				$pos++;
				continue;
			}

			$key = $closer->char . ($closer->canOpen ? 'o' : '-') . ($closer->origLength % 3);
			$bottom = $openersBottom[$key] ?? -1;
			$openerPos = null;

			for ($i = $pos - 1; $i > $bottom; $i--) {
				$candidate = $delims[$i];
				if ($candidate->char !== $closer->char || !$candidate->canOpen) {
					continue;
				}

				// the "rule of three"
				$forbidden = ($candidate->canClose || $closer->canOpen)
					&& ($candidate->origLength + $closer->origLength) % 3 === 0
					&& !($candidate->origLength % 3 === 0 && $closer->origLength % 3 === 0);

				if (!$forbidden) {
					$openerPos = $i;
					break;
				}
			}

			if ($openerPos === null) {
				$openersBottom[$key] = $pos - 1;
				if ($closer->canOpen) {
					$pos++;
				} else {
					array_splice($delims, $pos, 1);
				}

				continue;
			}

			$opener = $delims[$openerPos];
			$use = $opener->remaining >= 2 && $closer->remaining >= 2 ? 2 : 1;

			$from = self::indexOf($nodes, $opener);
			$to = self::indexOf($nodes, $closer);
			$phrase = new Nodes\PhraseNode(
				new Nodes\ContentNode(array_slice($nodes, $from + 1, $to - $from - 1)),
				$use === 2 ? Syntax::Strong : Syntax::Emphasis,
			);

			$opener->remaining -= $use;
			$closer->remaining -= $use;
			$opener->text = substr($opener->text, 0, $opener->remaining);
			$closer->text = substr($closer->text, $use);

			$nodes = [...array_slice($nodes, 0, $from + 1), $phrase, ...array_slice($nodes, $to)];

			// delimiters inside the new pair can never match again
			array_splice($delims, $openerPos + 1, $pos - $openerPos - 1);
			$pos = $openerPos + 1;

			if ($closer->remaining === 0) {
				$nodes = array_values(array_filter($nodes, fn($node) => $node !== $closer));
				array_splice($delims, $pos, 1);
			}

			if ($opener->remaining === 0) {
				$nodes = array_values(array_filter($nodes, fn($node) => $node !== $opener));
				array_splice($delims, $openerPos, 1);
				$pos--;
			}
		}

		$container->children = array_map(
			fn($node) => $node instanceof DelimiterNode ? new Nodes\TextNode($node->text, $node->range) : $node,
			$nodes,
		);
	}


	/** @param  array<Node>  $nodes */
	private static function indexOf(array $nodes, Node $needle): int
	{
		$pos = array_search($needle, $nodes, true);
		return is_int($pos)
			? $pos
			: throw new \LogicException('Delimiter is no longer among the container children.');
	}
}
