<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Parsing;

use Texy\Nodes\ContentNode;


/**
 * Mutable state of one parse: the link reference definitions LinkDefinitionPass collects.
 * Syntax handlers hold this object and Markdown::parse() resets it, so a Markdown instance
 * is reusable but not reentrant.
 *
 * @internal
 */
final class ParseState
{
	/** @var array<string, array{url: string, title: ?string}> */
	private array $definitions = [];

	/** @var array<string, ContentNode>  texts of links parsed in the inline content being read, by their position */
	private array $linkTexts = [];


	public function reset(): void
	{
		$this->definitions = [];
		$this->linkTexts = [];
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


	/** Positions in another inline content mean other texts. */
	public function forgetLinkTexts(): void
	{
		$this->linkTexts = [];
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
