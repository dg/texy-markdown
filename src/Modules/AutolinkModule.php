<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Modules;

use Texy;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Parsing\Registry;
use Texy\Range;
use function strlen;


/**
 * `<https://example.com>` and `<user@example.com>`.
 *
 * @internal
 */
final class AutolinkModule extends Texy\Module
{
	public function register(Registry $registry): void
	{
		$registry->addInline(
			Syntax::Autolink,
			'~<([a-zA-Z][a-zA-Z0-9+.-]{1,31}:[^<>[:cntrl:] ]*)>~',
			$this->parseUrl(...),
		);

		$registry->addInline(
			Syntax::AutolinkEmail,
			'~<([a-zA-Z0-9.!#$%&\'*+/=?^_`{|}\~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?'
			. '(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*+)>~',
			$this->parseEmail(...),
		);
	}


	/**
	 * @param  array{string, string}  $m
	 * @param  array{int, int}  $offsets
	 */
	private function parseUrl(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		return self::createLink($m[1], $m[1], $offsets[0], strlen($m[0]));
	}


	/**
	 * @param  array{string, string}  $m
	 * @param  array{int, int}  $offsets
	 */
	private function parseEmail(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		return self::createLink('mailto:' . $m[1], $m[1], $offsets[0], strlen($m[0]));
	}


	private static function createLink(string $url, string $label, int $offset, int $length): Nodes\LinkNode
	{
		return new Nodes\LinkNode(
			$url,
			new Nodes\ContentNode([new Nodes\TextNode($label)]),
			range: new Range($offset, $length),
		);
	}
}
