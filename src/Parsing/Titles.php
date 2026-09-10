<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Parsing;

use Texy\Modifier;


/**
 * Markdown titles in the slots Texy's own syntax fills with them.
 *
 * Neither LinkNode nor ImageNode carries a title of its own; a modifier does. A link's
 * title is the title of its modifier, as "text .(title)":url writes it. An image's is the
 * attribute of that name, as [* image {title: …} *] writes it, because the parentheses of
 * an image hold its alternative text, which is ImageNode::$alt.
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
