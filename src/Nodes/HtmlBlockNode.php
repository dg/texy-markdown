<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Nodes;

use Texy\Nodes\BlockNode;
use Texy\Range;


/**
 * A raw HTML block, passed through verbatim.
 *
 * The literal source is kept as a string rather than decomposed into a tag with
 * attributes: Markdown's contract is byte-identical passthrough, and rebuilding a
 * tag from an attribute map normalizes quoting and whitespace.
 */
class HtmlBlockNode extends BlockNode
{
	public function __construct(
		public string $html,
		public ?Range $range = null,
	) {
	}
}
