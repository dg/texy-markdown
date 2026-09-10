<?php declare(strict_types=1);

/**
 * Renders the AST as HTML byte-identically to the CommonMark reference implementation.
 *
 * This is a measuring instrument, not a product: the package's own HTML output goes
 * through Texy's renderer, which indents, wraps and well-forms. The spec expresses its
 * expected results as HTML, so proving the parser right needs a renderer that matches
 * the reference implementation exactly - and only the test suite needs that.
 */

namespace Texy\Markdown\Tests;

use Texy\Node;
use Texy\Nodes as TexyNodes;


final class SpecRenderer
{
	/** characters the reference implementation leaves unencoded inside a URL */
	private const HrefSafe = "-_.+!*'(),%#@?=;:/,&$~";

	private string $source;


	/** @param  string  $source  the text the ranges of the document are offsets in, i.e. Markdown::normalize() */
	public function render(TexyNodes\DocumentNode $document, string $source): string
	{
		$this->source = $source;
		return $this->renderBlocks($document->content->children);
	}


	/** @param  array<Node>  $nodes */
	private function renderBlocks(array $nodes, bool $tight = false): string
	{
		$res = '';
		foreach ($nodes as $node) {
			$res .= $this->renderBlock($node, $tight);
		}

		return $res;
	}


	private function renderBlock(Node $node, bool $tight = false): string
	{
		switch (true) {
			case $node instanceof TexyNodes\ParagraphNode:
				$inner = $this->renderInlines($node->content->children);
				return $tight ? $inner . "\n" : "<p>$inner</p>\n";

			case $node instanceof TexyNodes\HeadingNode:
				$level = $node->level;
				return "<h$level>" . $this->renderInlines($node->content->children) . "</h$level>\n";

			case $node instanceof TexyNodes\HorizontalRuleNode:
				return "<hr />\n";

			case $node instanceof TexyNodes\CodeBlockNode:
				$class = $node->language === null ? '' : ' class="language-' . self::escape($node->language) . '"';
				// a block of no line and one of a blank line both have empty code; only the first maps a line into the source
				$hasLines = $node->code !== '' || $node->codeMap?->toAbsolute(0) > 0;
				return "<pre><code$class>" . self::escape($hasLines ? $node->code . "\n" : '') . "</code></pre>\n";

			case $node instanceof TexyNodes\HtmlBlockNode:
				return $node->code . "\n";

			case $node instanceof TexyNodes\BlockQuoteNode:
				return "<blockquote>\n" . $this->renderBlocks($node->content->children) . "</blockquote>\n";

			case $node instanceof TexyNodes\ListNode:
				return $this->renderList($node);

			case $node instanceof TexyNodes\ContentNode:
				return $this->renderBlocks($node->children, $tight);

			default:
				return '';
		}
	}


	private function renderList(TexyNodes\ListNode $node): string
	{
		$tight = $node->paragraphs === TexyNodes\ItemParagraphs::Unwrapped;
		$tag = $node->type->isOrdered() ? 'ol' : 'ul';
		$start = $node->start === null || $node->start === 1 ? '' : ' start="' . $node->start . '"';
		$res = "<$tag$start>\n";

		foreach ($node->items as $item) {
			$children = $item->content->children;
			$inner = $this->renderBlocks($children, $tight);
			if ($inner === '') {
				$res .= "<li></li>\n";
				continue;
			}

			// a tight item opens/closes on the content line only when the edge child is a paragraph
			$flatStart = $tight && $children[0] instanceof TexyNodes\ParagraphNode;
			$flatEnd = $tight && end($children) instanceof TexyNodes\ParagraphNode;
			$res .= '<li>' . ($flatStart ? '' : "\n")
				. ($flatEnd ? rtrim($inner, "\n") : rtrim($inner, "\n") . "\n")
				. "</li>\n";
		}

		return $res . "</$tag>\n";
	}


	/** @param  array<Node>  $nodes */
	private function renderInlines(array $nodes): string
	{
		$res = '';
		foreach ($nodes as $node) {
			$res .= $this->renderInline($node);
		}

		return $res;
	}


	private function renderInline(Node $node): string
	{
		switch (true) {
			case $node instanceof TexyNodes\TextNode:
				return self::escape($node->text);

			case $node instanceof TexyNodes\RawTextNode:
				return $node->text;

			case $node instanceof TexyNodes\HtmlCommentNode:
				return substr($this->source, $node->range->offset, $node->range->length);

			case $node instanceof TexyNodes\SoftBreakNode:
				return "\n";

			case $node instanceof TexyNodes\LineBreakNode:
				return "<br />\n";

			case $node instanceof TexyNodes\HtmlTagNode:
				return (string) $node->source;

			case $node instanceof TexyNodes\PhraseNode:
				$inner = $this->renderInlines($node->content->children);
				return match ($node->type) {
					TexyNodes\PhraseType::Emphasis => "<em>$inner</em>",
					TexyNodes\PhraseType::Strong => "<strong>$inner</strong>",
					default => $inner,
				};

			case $node instanceof TexyNodes\InlineCodeNode:
				return '<code>' . self::escape($node->code) . '</code>';

			case $node instanceof TexyNodes\LinkNode:
				return '<a href="' . self::escapeHref((string) $node->url) . '"' . self::title($node->modifier?->title) . '>'
					. $this->renderInlines($node->content->children) . '</a>';

			case $node instanceof TexyNodes\ImageNode:
				$title = $node->modifier?->attrs['title'] ?? null;
				return '<img src="' . self::escapeHref((string) $node->url) . '"'
					. ' alt="' . self::escape($node->alt ?? '') . '"'
					. self::title(is_string($title) ? $title : null) . ' />';

			case $node instanceof TexyNodes\ContentNode:
				return $this->renderInlines($node->children);

			default:
				return '';
		}
	}


	private static function title(?string $title): string
	{
		return $title === null ? '' : ' title="' . self::escape($title) . '"';
	}


	private static function escape(string $s): string
	{
		return str_replace(['&', '<', '>', '"'], ['&amp;', '&lt;', '&gt;', '&quot;'], $s);
	}


	private static function escapeHref(string $s): string
	{
		$res = '';
		foreach (str_split($s) as $char) {
			$res .= ctype_alnum($char) || str_contains(self::HrefSafe, $char)
				? $char
				: sprintf('%%%02X', ord($char));
		}

		return str_replace(['&', '\'', '<', '>', '"'], ['&amp;', '&#x27;', '&lt;', '&gt;', '&quot;'], $res);
	}
}
