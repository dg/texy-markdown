<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Output\Markdown;


/**
 * How a heading of level 1 or 2 is written; deeper ones are always ATX.
 */
enum HeadingStyle
{
	/** `## Heading` */
	case Atx;

	/** text underlined with `===` or `---` */
	case Setext;
}


enum CodeBlockStyle
{
	case Fenced;
	case Indented;
}


enum LinkStyle
{
	/** `[text](url "title")` */
	case Inline;

	/** `[text][1]`, with the definitions at the end of the document */
	case Reference;
}
