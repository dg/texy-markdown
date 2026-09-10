<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Passes;

use Texy\Markdown\Parsing\PendingImageNode;
use Texy\Markdown\Parsing\Titles;
use Texy\Node;
use Texy\Nodes as TexyNodes;


/**
 * Turns the parse-time image node into the core one.
 *
 * An image's alt text is inline content while it is being parsed - it goes through
 * delimiter pairing like anything else - but its final form is plain text, which is
 * what the core ImageNode holds and why that node has no children. So the package
 * keeps its own image only until the emphasis pass is done, and flattens it here.
 *
 * Runs last, and no Markdown image node survives it.
 *
 * @internal
 */
final class ImagePass
{
	public function process(TexyNodes\DocumentNode $document): void
	{
		$this->walk($document);
	}


	private function walk(Node $node): void
	{
		if ($node instanceof TexyNodes\ContentNode) {
			foreach ($node->children as $i => $child) {
				if ($child instanceof PendingImageNode) {
					$node->children[$i] = self::convert($child);
				}
			}
		}

		foreach ($node->getChildren() as $child) {
			$this->walk($child);
		}
	}


	private static function convert(PendingImageNode $image): TexyNodes\ImageNode
	{
		return new TexyNodes\ImageNode(
			$image->url,
			alt: self::plainText($image->alt),
			modifier: Titles::forImage($image->title),
			range: $image->range,
		);
	}


	/** Nested markup contributes only the text it holds. */
	private static function plainText(TexyNodes\ContentNode $content): string
	{
		$res = '';
		foreach ($content->children as $node) {
			$res .= match (true) {
				$node instanceof TexyNodes\TextNode, $node instanceof TexyNodes\RawTextNode => $node->text,
				$node instanceof TexyNodes\InlineCodeNode => $node->code,
				$node instanceof TexyNodes\SoftBreakNode => "\n",
				$node instanceof TexyNodes\LineBreakNode => "\n",
				$node instanceof TexyNodes\PhraseNode => self::plainText($node->content),
				$node instanceof TexyNodes\LinkNode => self::plainText($node->content),
				$node instanceof PendingImageNode => self::plainText($node->alt),
				default => '',
			};
		}

		return $res;
	}
}
