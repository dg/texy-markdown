<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Internal;


/**
 * A group of related syntaxes that registers its patterns on the engine.
 *
 * @internal
 */
interface SyntaxRegistrar
{
	function register(): void;
}
