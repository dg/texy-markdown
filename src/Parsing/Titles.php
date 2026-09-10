<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Parsing;

use Texy\Modifier;


/**
 * Markdown titles in the slots Texy's own syntax fills with them: a link's is the title of its
 * modifier, an image's the attribute of that name.
 *
 * @internal
 */
final class Titles
{
	public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . self::class);
	}


	/** A link's title, or null when there is none and no modifier is needed. */
	public static function forLink(?string $title): ?Modifier
	{
		return $title === null ? null : new Modifier(title: $title);
	}


	/** An image's title, or null when there is none and no modifier is needed. */
	public static function forImage(?string $title): ?Modifier
	{
		return $title === null ? null : new Modifier(attrs: ['title' => $title]);
	}
}
