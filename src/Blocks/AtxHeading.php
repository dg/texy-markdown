<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Blocks;

use Texy\Markdown\Internal\SyntaxRegistrar;
use Texy\Markdown\Nodes\DeferredNode;
use Texy\Markdown\Registry;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Range;
use function strlen;


/**
 * `### heading`. The content cannot be inline-parsed here, see DeferredNode.
 *
 * @internal
 */
final class AtxHeading implements SyntaxRegistrar
{
	public function __construct(
		private Registry $registry,
	) {
	}


	public function register(): void
	{
		$this->registry->addBlock(
			Syntax::HeadingAtx,
			'~^[ ]{0,3}(\#{1,6})(?:[ \t]+([^\n]*?))??(?:[ \t]+\#+)?[ \t]*$~',
			$this->parse(...),
		);
	}


	/**
	 * @param  array{string, string, ?string}  $m
	 * @param  array{int, int, ?int}  $offsets
	 */
	private function parse(ParseContext $ctx, array $m, array $offsets): ?Nodes\BlockNode
	{
		$content = $m[2] ?? '';
		// "#5 bolt" is not a heading: a space must follow the hashes
		if ($content === '' && !preg_match('~^[ ]{0,3}\#{1,6}[ \t]*(\#+[ \t]*)?$~', $m[0])) {
			return null;
		}

		$content = rtrim($content);
		if (preg_match('~^(.*?)(?<!\\\)[ \t]+\#+$~', $content, $trailing)) {
			$content = rtrim($trailing[1]); // closing sequence of hashes
		}

		return new Nodes\HeadingNode(
			DeferredNode::wrap($content, $offsets[2] ?? $offsets[0]),
			strlen($m[1]),
			Nodes\HeadingType::Surrounded,
			null,
			new Range($offsets[0], strlen($m[0])),
		);
	}
}
