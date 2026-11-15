<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Parsing;

use Texy\Nodes\ContentNode;
use Texy\Nodes\LinkNode;
use function array_key_exists;


/**
 * Mutable state of one parse: the link reference definitions LinkDefinitionPass collects, and
 * what the links of the inline content being read leave behind by their position. Syntax
 * handlers hold this object and Markdown::parse() resets it, so a Markdown instance is
 * reusable but not reentrant.
 *
 * @internal
 */
final class ParseState
{
	/** @var array<string, array{url: string, title: ?string}> */
	private array $definitions = [];

	/** @var array<string, ContentNode>  texts of links parsed in the inline content being read, by their position */
	private array $linkTexts = [];

	/** @var array<string, ?LinkNode>  links the resolver was asked about in the inline content being read, by their position */
	private array $resolvedLinks = [];

	/** @var array<int, true>  brackets of image references without a definition in the inline content being read, by their position */
	private array $imageBrackets = [];


	public function reset(): void
	{
		$this->definitions = [];
		$this->forgetPositions();
	}


	/**
	 * The parsed text of a link, read once however many times the brackets around it are tried:
	 * a refused link is searched again from its next character, which lies inside its text.
	 * @param  \Closure(): ContentNode  $parse
	 */
	public function readLinkText(int $offset, int $length, \Closure $parse): ContentNode
	{
		return $this->linkTexts["$offset:$length"] ??= $parse();
	}


	/**
	 * A link the resolver finishes, or null when it refuses it, asked once however many times the
	 * link is tried: a link inside the text of a refused one is tried again.
	 * @param  \Closure(): ?LinkNode  $resolve
	 */
	public function resolveLink(int $offset, int $length, \Closure $resolve): ?LinkNode
	{
		$key = "$offset:$length";
		return array_key_exists($key, $this->resolvedLinks)
			? $this->resolvedLinks[$key]
			: $this->resolvedLinks[$key] = $resolve();
	}


	/**
	 * The brackets of an image reference without a definition, of its text and of its label,
	 * are no link for the link resolver: the reference stays text.
	 */
	public function addUnresolvedImage(int $text, ?int $label): void
	{
		$this->imageBrackets[$text] = true;
		if ($label !== null) {
			$this->imageBrackets[$label] = true;
		}
	}


	public function isImageBracket(int $offset): bool
	{
		return isset($this->imageBrackets[$offset]);
	}


	/** Positions in another inline content mean other links. */
	public function forgetPositions(): void
	{
		$this->linkTexts = [];
		$this->resolvedLinks = [];
		$this->imageBrackets = [];
	}


	/** The first definition of a label wins. */
	public function addDefinition(string $label, string $url, ?string $title): void
	{
		$this->definitions[Escaping::normalizeLabel($label)] ??= ['url' => $url, 'title' => $title];
	}


	/** @return ?array{url: string, title: ?string} */
	public function findDefinition(string $label): ?array
	{
		return $this->definitions[Escaping::normalizeLabel($label)] ?? null;
	}
}
