<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Nodes;

use Texy\Nodes\InlineNode;
use Texy\Range;


/**
 * Raw inline HTML, passed through verbatim. See HtmlBlockNode for why it is a string.
 */
class RawInlineNode extends InlineNode
{
	public function __construct(
		public string $html,
		public ?Range $range = null,
	) {
	}
}
