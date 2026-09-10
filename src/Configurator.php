<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown;

use Texy\Texy;


/**
 * Ready-made configurations.
 */
final class Configurator
{
	public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . self::class);
	}


	/**
	 * Configures a parser and the Texy that renders it for untrusted input.
	 *
	 * Raw HTML is switched off at the parser rather than sanitized at the renderer.
	 * Markdown's raw HTML is kept as literal source - that is what byte-identical
	 * passthrough means - so there is no tag model to run a whitelist over, and text
	 * that is never parsed as markup cannot smuggle any through. Images are switched off
	 * there too, because Texy's safe mode does that only for its own syntax. Everything
	 * else, the tag and attribute whitelist for constructs Texy itself renders, the URL
	 * scheme filter, nofollow, comes from Texy's own safe mode.
	 *
	 * Note that CommonMark says nothing about HTML sanitization; toHtml() without this
	 * is spec-correct, not lax by oversight.
	 */
	public static function safeMode(Markdown $markdown, Texy $texy): void
	{
		\Texy\Configurator::safeMode($texy);
		$markdown->allowed[Syntax::HtmlInline] = false;
		$markdown->allowed[Syntax::HtmlBlock] = false;
		$markdown->allowed[Syntax::Image] = false;
	}
}
