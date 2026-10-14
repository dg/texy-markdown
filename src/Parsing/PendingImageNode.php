<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Parsing;

use Texy\Nodes;
use Texy\Range;


/**
 * An image. Its alt text is a node list, because Markdown allows markup inside it
 * and the markup has to go through delimiter pairing before it can be flattened.
 */
class PendingImageNode extends Nodes\InlineNode
{
	public function __construct(
		public string $url,
		public Nodes\ContentNode $alt,
		public ?string $title = null,
		public ?Range $range = null,
		public ?Range $urlRange = null,
	) {
	}


	public function &getChildren(): \Generator
	{
		yield $this->alt;
	}
}
