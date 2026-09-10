<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Passes;

use Texy\Markdown\Parsing\DeferredNode;
use Texy\Markdown\Parsing\Escaping;
use Texy\Markdown\Parsing\ParseState;
use Texy\Markdown\Parsing\Patterns;
use Texy\Node;
use Texy\Nodes;
use function strlen;


/**
 * Collects link reference definitions.
 *
 * They are stripped from the *front of a paragraph*, which is where the spec puts
 * them, and not from the raw gap text: a construct that may not interrupt a paragraph
 * still splits the gap into two calls, and only the finished paragraph has all its
 * lines together. A paragraph that is nothing but definitions disappears.
 *
 * Runs before InlineParsePass, so every definition is known before the first bracket
 * is looked at.
 *
 * @internal
 */
final class LinkDefinitionPass
{
	public function __construct(
		private ParseState $state,
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
			$res = [];
			foreach ($node->children as $child) {
				if (!$child instanceof Nodes\ParagraphNode || $this->strip($child)) {
					$res[] = $child;
				}
			}

			$node->children = $res;
		}

		foreach ($node->getChildren() as $child) {
			$this->walk($child);
		}
	}


	/** Strips the definitions leading the paragraph; false when nothing is left of it. */
	private function strip(Nodes\ParagraphNode $paragraph): bool
	{
		$deferred = $paragraph->content->children[0] ?? null;
		if (!$deferred instanceof DeferredNode) {
			return true;
		}

		$text = $deferred->text;
		$consumed = 0;
		while (($definition = self::match(substr($text, $consumed))) !== null) {
			[$length, $label, $dest, $title] = $definition;
			$this->state->addDefinition($label, Escaping::cleanDestination($dest), Escaping::cleanTitle($title));
			$consumed += $length;
		}

		if ($consumed === 0) {
			return true;
		}

		$deferred->cut($consumed);
		$paragraph->range = $deferred->range;
		return $deferred->text !== '';
	}


	/**
	 * One definition at the start of $text.
	 * @return ?array{int, string, string, ?string}  consumed length, label, destination, title
	 */
	private static function match(string $text): ?array
	{
		if (!preg_match('~^[ ]{0,3}\[((?:[^\[\]\\\]|\\\.){1,999}+)\]:~', $text, $m)) {
			return null;
		}

		$label = $m[1];
		if (trim($label) === '') {
			return null;
		}

		$pos = self::skipSpaces($text, strlen($m[0]));
		if (!preg_match('~<(?:[^<>\n\\\]++|\\\.)*+>|[^\s<[:cntrl:]][^\s[:cntrl:]]*~A', $text, $d, 0, $pos)) {
			return null;
		}

		$dest = $d[0];
		$afterDest = $pos + strlen($dest);

		// the title may sit on the next line, but only if the definition then ends there
		$titlePos = self::skipSpaces($text, $afterDest);
		if ($titlePos > $afterDest
			&& preg_match('~' . Patterns::LinkTitle . '~A', $text, $t, 0, $titlePos)
			&& ($end = self::endOfLine($text, $titlePos + strlen($t[0]))) !== null
		) {
			return [$end, $label, $dest, $t[0]];
		}

		$end = self::endOfLine($text, $afterDest);
		return $end === null ? null : [$end, $label, $dest, null];
	}


	/** Skips spaces, tabs and at most one newline. */
	private static function skipSpaces(string $text, int $pos): int
	{
		$pos += strspn($text, " \t", $pos);
		if (($text[$pos] ?? '') === "\n") {
			$pos += 1 + strspn($text, " \t", $pos + 1);
		}

		return $pos;
	}


	/** Position after the line, provided only whitespace remains on it. */
	private static function endOfLine(string $text, int $pos): ?int
	{
		$pos += strspn($text, " \t", $pos);
		return match ($text[$pos] ?? '') {
			'' => $pos,
			"\n" => $pos + 1,
			default => null,
		};
	}
}
