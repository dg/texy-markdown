<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Inlines;

use Texy\Markdown\Internal\SyntaxRegistrar;
use Texy\Markdown\Registry;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Range;
use function strlen;


/**
 * `<https://example.com>` and `<user@example.com>`.
 *
 * @internal
 */
final class Autolinks implements SyntaxRegistrar
{
	public function __construct(
		private Registry $registry,
	) {
	}


	public function register(): void
	{
		$this->registry->addLine(
			Syntax::Autolink,
			'~<([a-zA-Z][a-zA-Z0-9+.-]{1,31}:[^<>[:cntrl:] ]*)>~',
			$this->parseUrl(...),
		);

		$this->registry->addLine(
			Syntax::AutolinkEmail,
			'~<([a-zA-Z0-9.!#$%&\'*+/=?^_`{|}\~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?'
			. '(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*)>~',
			$this->parseEmail(...),
		);
	}


	/**
	 * @param  array{string, string}  $m
	 * @param  array{int, int}  $offsets
	 */
	private function parseUrl(ParseContext $ctx, array $m, array $offsets, string $name): Nodes\InlineNode
	{
		return self::link($m[1], $m, $offsets, $name);
	}


	/**
	 * @param  array{string, string}  $m
	 * @param  array{int, int}  $offsets
	 */
	private function parseEmail(ParseContext $ctx, array $m, array $offsets, string $name): Nodes\InlineNode
	{
		return self::link('mailto:' . $m[1], $m, $offsets, $name);
	}


	/**
	 * The address between the pointy brackets is both the text and the target.
	 * @param  array{string, string}  $m
	 * @param  array{int, int}  $offsets
	 */
	private static function link(string $url, array $m, array $offsets, string $name): Nodes\LinkNode
	{
		$address = new Range($offsets[1], strlen($m[1]));
		return new Nodes\LinkNode(
			$url,
			new Nodes\ContentNode([new Nodes\TextNode($m[1], $address)]),
			range: new Range($offsets[0], strlen($m[0])),
			syntax: $name,
			textRange: $address,
			urlRange: $address,
		);
	}
}
