<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Nodes;

use Texy\Nodes;


/**
 * A list that knows whether it is tight: a tight item renders its content without
 * a paragraph wrapper. Markdown has no other way to express that, and the core
 * ListNode has no slot for it.
 */
class ListNode extends Nodes\ListNode
{
	public bool $tight = true;
}
