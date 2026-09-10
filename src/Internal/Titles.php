<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Internal;

use Texy\Modifier;


/**
 * Markdown titles in the slots the Texy AST already has for them.
 *
 * Neither LinkNode nor ImageNode carries a title of its own; a modifier does, and both
 * renderers already read it. An image is the awkward one, because Markdown gives it two
 * strings: the alt text goes where Texy puts an image's alt (the modifier title) and the
 * title into the attribute of that name, which is exactly the pair
 * Output\Html\Renderer::renderImage() reads.
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
		if ($title === null) {
			return null;
		}

		$modifier = new Modifier;
		$modifier->title = $title;
		return $modifier;
	}


	/** An image's alt text and, separately, its title. */
	public static function forImage(string $alt, ?string $title): Modifier
	{
		$modifier = new Modifier;
		$modifier->title = $alt;
		if ($title !== null) {
			$modifier->attrs['title'] = $title;
		}

		return $modifier;
	}
}
