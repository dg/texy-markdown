<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Internal;

use function strlen;


/**
 * Whether the last block a container has open is a paragraph.
 *
 * That is the whole condition for a lazy continuation line, and "the previous line was
 * not blank" is not the same question: after an indented code block or inside an open
 * fence there is no paragraph to continue, and the container has to end instead.
 *
 * @internal
 */
final class LineState
{
	private const None = 0;
	private const Paragraph = 1;
	private const Code = 2;
	private const Fence = 3;

	private int $state = self::None;
	private string $fence = '';


	public function inParagraph(): bool
	{
		return $this->state === self::Paragraph;
	}


	public function feed(string $line): void
	{
		if ($this->state === self::Fence) {
			if (preg_match('~^[ ]{0,3}' . preg_quote($this->fence[0], '~') . '{' . strlen($this->fence) . ',}[ \t]*$~', $line)) {
				$this->state = self::None;
			}

			return;
		}

		if (trim($line) === '') {
			$this->state = self::None;

		} elseif (preg_match('~^[ ]{0,3}(`{3,}|\~{3,})~', $line, $m)) {
			$this->state = self::Fence;
			$this->fence = $m[1];

		} elseif (preg_match('~^[ ]{0,3}(?:>[ ]?|(?:[-+*]|\d{1,9}[.)])(?:[ \t]+|$))~', $line, $m)) {
			// a container marker decides nothing by itself - its content does
			$this->feed(substr($line, strlen($m[0])));

		} elseif (Patterns::startsBlock($line)) {
			$this->state = self::None;

		} elseif ($this->state !== self::Paragraph) {
			$this->state = Indentation::width($line) >= Indentation::TabStop ? self::Code : self::Paragraph;
		}
	}
}
