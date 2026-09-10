<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Modules;

use Texy;
use Texy\Helpers;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Parsing\Registry;
use Texy\Range;
use function strlen;


/**
 * `&amp;`, `&#35;`, `&#x22;`.
 *
 * @internal
 */
final class EntityModule extends Texy\Module
{
	public function register(Registry $registry): void
	{
		$registry->addInline(
			Syntax::Entity,
			'~' . Helpers::EntityPattern . '~',
			$this->parse(...),
		);
	}


	/**
	 * @param  array{string}  $m
	 * @param  array{int}  $offsets
	 */
	private function parse(ParseContext $ctx, array $m, array $offsets): ?Nodes\InlineNode
	{
		$decoded = Helpers::decodeEntity($m[0]);
		return $decoded === null
			? null
			: new Nodes\TextNode($decoded, new Range($offsets[0], strlen($m[0])));
	}
}
