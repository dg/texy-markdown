<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Blocks;

use Texy\Markdown\Internal\Patterns;
use Texy\Markdown\Internal\SyntaxRegistrar;
use Texy\Markdown\Nodes\DeferredNode;
use Texy\Markdown\Registry;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Range;
use function strlen;


/**
 * A paragraph underlined with `===` or `---`.
 *
 * Registered after the list and the thematic break on purpose: all three can match
 * at the same offset and the block parser then decides by registration order.
 *
 * @internal
 */
final class SetextHeading implements SyntaxRegistrar
{
	public function __construct(
		private Registry $registry,
	) {
	}


	public function register(): void
	{
		$excluded = '(?!' . Patterns::SetextExcluded . ')';
		$this->registry->addBlock(
			Syntax::HeadingSetext,
			'~^(' . $excluded . '[ ]{0,3}\S[^\n]*\n(?:' . $excluded . '[ \t]*\S[^\n]*\n)*)[ ]{0,3}(=+|-+)[ \t]*$~',
			$this->parse(...),
		);
	}


	/**
	 * @param  array{string, string, string}  $m
	 * @param  array{int, int, int}  $offsets
	 */
	private function parse(ParseContext $ctx, array $m, array $offsets): Nodes\BlockNode
	{
		$deferred = null;
		$offset = $offsets[1];
		foreach (explode("\n", rtrim($m[1], "\n")) as $line) {
			$start = $offset + strspn($line, " \t");
			if ($deferred === null) {
				$deferred = new DeferredNode(trim($line), $start);
			} else {
				$deferred->append(trim($line), $start);
			}

			$offset += strlen($line) + 1;
		}

		return new Nodes\HeadingNode(
			new Nodes\ContentNode([$deferred]),
			$m[2][0] === '=' ? 1 : 2,
			Nodes\HeadingType::Underlined,
			null,
			new Range($offsets[0], strlen($m[0])),
		);
	}
}
