<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown;

use Texy\Markdown\Internal\Escaping;


/**
 * Mutable state of one parse: the enabled syntaxes and the link reference definitions
 * LinkDefinitionPass collects. Syntax handlers hold this object and Markdown::parse()
 * resets it, so a Markdown instance is reusable but not reentrant.
 *
 * The enabled map lives here because the engine can only filter *patterns* by it, and
 * not every construct is one - the gap handler is reached whenever the block patterns
 * claim nothing, so what it recognizes it has to check for itself.
 *
 * @internal
 */
final class ParseState
{
	/** @var array<string, array{url: string, title: ?string}> */
	private array $definitions = [];

	/** @var array<string, bool> */
	private array $allowed = [];


	/** @param  array<string, bool>  $allowed */
	public function reset(array $allowed): void
	{
		$this->definitions = [];
		$this->allowed = $allowed;
	}


	public function isAllowed(string $syntax): bool
	{
		return !empty($this->allowed[$syntax]);
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
