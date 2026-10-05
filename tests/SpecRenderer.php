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

use Texy\Markdown\Nodes;
use Texy\Node;
use Texy\Nodes as TexyNodes;
use Texy\Syntax;


final class SpecRenderer
{
	/** characters the reference implementation leaves unencoded inside a URL */
	private const HrefSafe = "-_.+!*'(),%#@?=;:/,&$~";


	public function render(TexyNodes\DocumentNode $document): string
	{
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
				return "<pre><code$class>" . self::escape($node->code) . "</code></pre>\n";

			case $node instanceof Nodes\HtmlBlockNode:
				return $node->html . "\n";

			case $node instanceof TexyNodes\BlockQuoteNode:
				return "<blockquote>\n" . $this->renderBlocks($node->content->children) . "</blockquote>\n";

			case $node instanceof Nodes\ListNode:
				return $this->renderList($node);

			case $node instanceof TexyNodes\TableNode:
				return $this->renderTable($node);

			case $node instanceof TexyNodes\ContentNode:
				return $this->renderBlocks($node->children, $tight);

			default:
				return '';
		}
	}


	private function renderList(Nodes\ListNode $node): string
	{
		$tag = $node->type->isOrdered() ? 'ol' : 'ul';
		$start = $node->start === null || $node->start === 1 ? '' : ' start="' . $node->start . '"';
		$res = "<$tag$start>\n";

		foreach ($node->items as $item) {
			$children = $item->content->children;
			$inner = $this->renderBlocks($children, $node->tight);
			if ($inner === '') {
				$res .= "<li></li>\n";
				continue;
			}

			// a tight item opens/closes on the content line only when the edge child is a paragraph
			$flatStart = $node->tight && $children[0] instanceof TexyNodes\ParagraphNode;
			$flatEnd = $node->tight && end($children) instanceof TexyNodes\ParagraphNode;
			$res .= '<li>' . ($flatStart ? '' : "\n")
				. ($flatEnd ? rtrim($inner, "\n") : rtrim($inner, "\n") . "\n")
				. "</li>\n";
		}

		return $res . "</$tag>\n";
	}


	private function renderTable(TexyNodes\TableNode $node): string
	{
		$head = $body = '';
		foreach ($node->rows as $row) {
			$cells = '';
			foreach ($row->cells as $cell) {
				$tag = $cell->header ? 'th' : 'td';
				$align = $cell->modifier?->hAlign;
				$cells .= "<$tag" . ($align === null ? '' : " align=\"$align\"") . '>'
					. $this->renderInlines($cell->content->children) . "</$tag>\n";
			}

			$markup = "<tr>\n$cells</tr>\n";
			if ($row->header) {
				$head .= $markup;
			} else {
				$body .= $markup;
			}
		}

		return "<table>\n<thead>\n$head</thead>\n"
			. ($body === '' ? '' : "<tbody>\n$body</tbody>\n")
			. "</table>\n";
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
			case $node instanceof TexyNodes\RawTextNode:
				return self::escape($node->text);

			case $node instanceof Nodes\RawInlineNode:
				return $node->html;

			case $node instanceof Nodes\SoftBreakNode:
				return "\n";

			case $node instanceof TexyNodes\LineBreakNode:
				return "<br />\n";

			case $node instanceof TexyNodes\HtmlTagNode:
				$attrs = '';
				foreach ($node->attributes as $name => $value) {
					$attrs .= ' ' . $name . '="' . self::escape((string) $value) . '"';
				}

				return '<' . $node->name . $attrs . '>';

			case $node instanceof TexyNodes\PhraseNode:
				$inner = $this->renderInlines($node->content->children);
				return match ($node->type) {
					Syntax::Emphasis => "<em>$inner</em>",
					Syntax::Strong => "<strong>$inner</strong>",
					Syntax::Code => "<code>$inner</code>",
					Syntax::Deleted => "<del>$inner</del>",
					default => $inner,
				};

			case $node instanceof TexyNodes\LinkNode:
				return '<a href="' . self::escapeHref((string) $node->url) . '"' . self::title($node->modifier?->title) . '>'
					. $this->renderInlines($node->content->children) . '</a>';

			case $node instanceof TexyNodes\ImageNode:
				$title = $node->modifier?->attrs['title'] ?? null;
				return '<img src="' . self::escapeHref((string) $node->url) . '"'
					. ' alt="' . self::escape((string) ($node->modifier?->title ?? '')) . '"'
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
