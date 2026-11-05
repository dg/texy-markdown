<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown;

use JetBrains\PhpStorm\Language;
use Texy\Nodes;
use Texy\Parsing\Engine;
use Texy\Parsing\ParseContext;


/**
 * Owns the engine and remembers which syntax name is a block and which is inline.
 *
 * There is one public enabled/disabled map (Markdown::$allowed); the split into a
 * block set and an inline set happens here, when a parse starts. That split is what
 * keeps the two parsing phases apart: the block phase gets an inline parser with no
 * patterns at all, so a block handler cannot accidentally parse inline content
 * before every link reference definition has been collected.
 *
 * @internal
 */
final class Registry
{
	private Engine $engine;

	/** @var array<string, bool> */
	private array $blockNames = [];

	/** @var array<string, bool> */
	private array $inlineNames = [];


	public function __construct()
	{
		$this->engine = new Engine;
	}


	/**
	 * Pattern handlers are intentionally polymorphic: each declares the exact capture
	 * shape of its own regexp, so no single signature fits them all.
	 */
	public function addBlock(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		callable $handler,
	): void
	{
		$this->blockNames[$name] = true;
		$this->engine->addBlockSyntax($name, $pattern, $handler(...));
	}


	/** See addBlock() for why the handler has no declared signature. */
	public function addLine(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		callable $handler,
	): void
	{
		$this->inlineNames[$name] = true;
		$this->engine->addLineSyntax($name, $pattern, $handler(...));
	}


	/** @param  callable(ParseContext, string, int): list<Nodes\BlockNode>  $handler */
	public function setGapHandler(callable $handler): void
	{
		$this->engine->setGapHandler($handler(...));
	}


	/**
	 * Parses the block structure. Inline syntaxes are switched off here on purpose.
	 * @param  array<string, bool>  $allowed
	 */
	public function parseBlocks(string $text, array $allowed): Nodes\DocumentNode
	{
		return $this->engine->parse($text, array_intersect_key($allowed, $this->blockNames));
	}


	/**
	 * Does the pattern of an allowed block syntax match the text?
	 * @param  array<string, bool>  $allowed
	 */
	public function matchesBlockSyntax(string $text, array $allowed): bool
	{
		return $this->engine->matchesBlockSyntax($text, array_intersect_key($allowed, $this->blockNames));
	}


	/**
	 * A context for the inline phase, which runs as a pass inside parse().
	 * @param  array<string, bool>  $allowed
	 */
	public function createInlineContext(array $allowed): ParseContext
	{
		return $this->engine->createParseContext(array_intersect_key($allowed, $this->inlineNames));
	}
}
