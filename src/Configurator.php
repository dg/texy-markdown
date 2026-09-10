<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown;


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
	 * Configures a parser for untrusted input: raw HTML stays and goes through the same
	 * whitelist as in the safe mode of Texy, comments are not passed on and an image shows
	 * only from http(s), any other renders as its alternative text.
	 */
	public static function safeMode(Markdown $markdown): void
	{
		\Texy\Configurator::restrictPolicies($markdown->htmlPolicy, $markdown->htmlOutput);
		$markdown->htmlOutput->passHtmlComments = false;
	}
}
