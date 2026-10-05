<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Passes;

use Texy\Markdown\Parsing\DelimiterNode;
use Texy\Node;
use Texy\Nodes;
use Texy\Range;


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
	/** the ends of a linked list, which are no position of a node */
	private const Head = -1;
	private const Tail = -2;


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
		// The children and the runs among them are linked lists, each keyed by the position
		// in $nodes: taking a pair out of an array would rebuild it for every pair.
		$nodes = $container->children;
		$runs = array_filter($nodes, fn($node) => $node instanceof DelimiterNode);
		if (!$runs) {
			return;
		}

		[$next, $prev] = self::link(array_keys($nodes));
		[$nextRun, $prevRun] = self::link(array_keys($runs));
		$openersBottom = [];
		$pos = $nextRun[self::Head];

		while ($pos !== self::Tail) {
			$closer = $runs[$pos];
			if (!$closer->canClose) {
				$pos = $nextRun[$pos];
				continue;
			}

			$key = $closer->char . ($closer->canOpen ? 'o' : '-') . ($closer->origLength % 3);
			$bottom = $openersBottom[$key] ?? self::Head;
			$openerPos = null;

			for ($i = $prevRun[$pos]; $i !== self::Head && $i !== $bottom; $i = $prevRun[$i]) {
				$candidate = $runs[$i];
				if ($candidate->char !== $closer->char || !$candidate->canOpen) {
					continue;
				}

				// GFM pairs tildes only with a run of the same length, and the rule of
				// three does not apply to them
				$forbidden = $closer->char === '~'
					? $candidate->origLength !== $closer->origLength
					: ($candidate->canClose || $closer->canOpen)
						&& ($candidate->origLength + $closer->origLength) % 3 === 0
						&& !($candidate->origLength % 3 === 0 && $closer->origLength % 3 === 0);

				if (!$forbidden) {
					$openerPos = $i;
					break;
				}
			}

			if ($openerPos === null) {
				$openersBottom[$key] = $prevRun[$pos];
				$following = $nextRun[$pos];
				if (!$closer->canOpen) {
					self::unlink($nextRun, $prevRun, $pos);
				}

				$pos = $following;
				continue;
			}

			$opener = $runs[$openerPos];
			$use = match (true) {
				$closer->char === '~' => $closer->remaining,
				$opener->remaining >= 2 && $closer->remaining >= 2 => 2,
				default => 1,
			};

			$content = [];
			for ($i = $next[$openerPos]; $i !== $pos; $i = $next[$i]) {
				$content[] = $nodes[$i];
			}

			$start = $opener->takeEnd($use);
			$end = $closer->takeStart($use);
			$nodes[] = new Nodes\PhraseNode(
				new Nodes\ContentNode($content),
				match (true) {
					$closer->char === '~' => Nodes\PhraseType::Deleted,
					$use === 2 => Nodes\PhraseType::Strong,
					default => Nodes\PhraseType::Emphasis,
				},
				range: $start && $end ? new Range($start->offset, $end->offset + $end->length - $start->offset) : null,
			);

			// the phrase stands between the two runs, in place of what it holds
			$phrase = array_key_last($nodes);
			$next[$openerPos] = $prev[$pos] = $phrase;
			$prev[$phrase] = $openerPos;
			$next[$phrase] = $pos;

			// runs inside the new pair can never match again
			$nextRun[$openerPos] = $pos;
			$prevRun[$pos] = $openerPos;

			if ($opener->remaining === 0) {
				self::unlink($next, $prev, $openerPos);
				self::unlink($nextRun, $prevRun, $openerPos);
			}

			if ($closer->remaining === 0) {
				$following = $nextRun[$pos];
				self::unlink($next, $prev, $pos);
				self::unlink($nextRun, $prevRun, $pos);
				$pos = $following;
			}
		}

		$children = [];
		for ($i = $next[self::Head]; $i !== self::Tail; $i = $next[$i]) {
			$node = $nodes[$i];
			$children[] = $node instanceof DelimiterNode ? new Nodes\TextNode($node->text, $node->range) : $node;
		}

		$container->children = $children;
	}


	/**
	 * Links the positions into a list between Head and Tail.
	 * @param  list<int>  $positions
	 * @return array{array<int, int>, array<int, int>}  the next and the previous position of each
	 */
	private static function link(array $positions): array
	{
		$next = $prev = [];
		$last = self::Head;
		foreach ([...$positions, self::Tail] as $position) {
			$next[$last] = $position;
			$prev[$position] = $last;
			$last = $position;
		}

		return [$next, $prev];
	}


	/**
	 * @param  array<int, int>  $next
	 * @param  array<int, int>  $prev
	 */
	private static function unlink(array &$next, array &$prev, int $position): void
	{
		$next[$prev[$position]] = $next[$position];
		$prev[$next[$position]] = $prev[$position];
	}
}
