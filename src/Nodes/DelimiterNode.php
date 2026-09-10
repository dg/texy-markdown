<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Nodes;

use Texy\Nodes\InlineNode;
use Texy\Range;
use function strlen;


/**
 * A run of `*` or `_` the parser left undecided.
 *
 * Whether a run opens emphasis, closes it or is literal text is a property of the
 * whole sequence of runs in a container, so the parser only marks the run and
 * records the spec's left/right-flanking classification; EmphasisPass does the
 * pairing. No DelimiterNode survives the parse phase.
 *
 * @internal
 */
class DelimiterNode extends InlineNode
{
	public string $char;

	/** length as written, which the "rule of three" is judged on even after characters are consumed */
	public int $origLength;
	public int $remaining;
	public bool $canOpen;
	public bool $canClose;


	public function __construct(
		public string $text,
		string $before,
		string $after,
		public ?Range $range = null,
	) {
		$this->char = $text[0];
		$this->origLength = $this->remaining = strlen($text);

		$beforeWs = $before === '' || (bool) preg_match('~^\s$~u', $before);
		$afterWs = $after === '' || (bool) preg_match('~^\s$~u', $after);
		$beforePunct = !$beforeWs && (bool) preg_match('~^[\p{P}\p{S}]$~u', $before);
		$afterPunct = !$afterWs && (bool) preg_match('~^[\p{P}\p{S}]$~u', $after);

		$leftFlanking = !$afterWs && (!$afterPunct || $beforeWs || $beforePunct);
		$rightFlanking = !$beforeWs && (!$beforePunct || $afterWs || $afterPunct);

		// _ is stricter than *, so that snake_case_words are not torn apart
		if ($this->char === '_') {
			$this->canOpen = $leftFlanking && (!$rightFlanking || $beforePunct);
			$this->canClose = $rightFlanking && (!$leftFlanking || $afterPunct);
		} else {
			$this->canOpen = $leftFlanking;
			$this->canClose = $rightFlanking;
		}
	}
}
