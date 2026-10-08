<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Output\Markdown;

use Texy\Helpers;
use Texy\Markdown\Markdown;
use Texy\Node;
use Texy\Nodes;
use Texy\Output\NodeRenderer;
use Texy\Regexp;
use Texy\UrlPolicy;
use function count, is_string, strlen;


/**
 * Generates Markdown (GFM) output from AST.
 * @extends NodeRenderer<string>
 */
final class Renderer extends NodeRenderer
{
	public HeadingStyle $headingStyle = HeadingStyle::Atx;

	public CodeBlockStyle $codeBlockStyle = CodeBlockStyle::Fenced;

	/** Fence character for code blocks */
	public string $codeFence = '```';

	/** Marker for unordered lists */
	public string $unorderedListMarker = '-';

	/** Delimiter for ordered lists */
	public string $orderedListDelimiter = '.';

	/** Emphasis marker */
	public string $emphasisMarker = '*';

	/** Strong marker */
	public string $strongMarker = '**';

	public LinkStyle $linkStyle = LinkStyle::Inline;

	/** Horizontal rule style */
	public string $horizontalRule = '---';

	/** Escape special characters in text */
	public bool $escapeSpecialChars = true;

	/** Shorten URLs in autolinks */
	public bool $shortenUrls = false;

	/** @var array<int|string, array{url: string, title: ?string}> collected link references */
	private array $linkReferences = [];

	private int $referenceCounter = 0;

	/** reads the printed paragraphs back, as a GFM parser does */
	private Markdown $reader;

	/** the list whose items are being rendered */
	private Nodes\ListNode|Nodes\DefinitionListNode|null $list = null;

	/** @var array{number: int, loose: bool}  how its next item is rendered */
	private array $item = ['number' => 1, 'loose' => false];


	public function __construct(
		private ?UrlPolicy $urlPolicy = null,
	) {
		$this->handlers = [
			// Core nodes
			Nodes\DocumentNode::class => fn(Nodes\DocumentNode $n, self $g) => $g->renderDocument($n),
			Nodes\TextNode::class => fn(Nodes\TextNode $n, self $g) => $g->renderText($n),
			Nodes\ContentNode::class => fn(Nodes\ContentNode $n, self $g) => $g->renderNodes($n->children),

			// Block nodes
			Nodes\ParagraphNode::class => fn(Nodes\ParagraphNode $n, self $g) => $g->renderParagraph($n),
			Nodes\HeadingNode::class => fn(Nodes\HeadingNode $n, self $g) => $g->renderHeading($n),
			Nodes\BlockQuoteNode::class => fn(Nodes\BlockQuoteNode $n, self $g) => $g->renderBlockQuote($n),
			Nodes\VerbatimBlockNode::class => fn(Nodes\VerbatimBlockNode $n, self $g) => $g->renderVerbatimBlock($n),
			Nodes\HtmlBlockNode::class => fn(Nodes\HtmlBlockNode $n, self $g) => $g->renderHtmlBlock($n),
			Nodes\DivNode::class => fn(Nodes\DivNode $n, self $g) => $g->renderDiv($n),
			Nodes\HorizontalRuleNode::class => fn(Nodes\HorizontalRuleNode $n, self $g) => $g->renderHorizontalRule($n),
			Nodes\FigureNode::class => fn(Nodes\FigureNode $n, self $g) => $g->renderFigure($n),

			// List nodes
			Nodes\ListNode::class => fn(Nodes\ListNode $n, self $g) => $g->renderList($n),
			Nodes\ListItemNode::class => fn(Nodes\ListItemNode $n, self $g) => $g->renderListItem($n),
			Nodes\DefinitionListNode::class => fn(Nodes\DefinitionListNode $n, self $g) => $g->renderDefinitionList($n),

			// Table nodes
			Nodes\TableNode::class => fn(Nodes\TableNode $n, self $g) => $g->renderTable($n),
			Nodes\TableRowNode::class => fn(Nodes\TableRowNode $n, self $g) => $g->renderTableRow($n),
			Nodes\TableCellNode::class => fn(Nodes\TableCellNode $n, self $g) => $g->renderTableCell($n),

			// Inline nodes
			Nodes\ImageNode::class => fn(Nodes\ImageNode $n, self $g) => $g->renderImage($n),
			Nodes\LinkNode::class => fn(Nodes\LinkNode $n, self $g) => $g->renderLink($n),
			Nodes\PhraseNode::class => fn(Nodes\PhraseNode $n, self $g) => $g->renderPhrase($n),
			Nodes\InlineCodeNode::class => fn(Nodes\InlineCodeNode $n) => self::codeSpan($n->code),
			Nodes\RawTextNode::class => fn(Nodes\RawTextNode $n) => self::escapeText($n->text),
			Nodes\AnnotationNode::class => fn(Nodes\AnnotationNode $n) => '<abbr title="'
				. htmlspecialchars($n->annotation, ENT_QUOTES, 'UTF-8')
				. '">'
				. $n->text
				. '</abbr>',
			Nodes\EmoticonNode::class => fn(Nodes\EmoticonNode $n) => $n->resolved ?? $n->emoticon,
			Nodes\LineBreakNode::class => fn() => "  \n",
			Nodes\SoftBreakNode::class => fn() => "\n",

			// Autolink nodes
			Nodes\UrlNode::class => fn(Nodes\UrlNode $n, self $g) => $g->renderUrl($n),
			Nodes\EmailNode::class => fn(Nodes\EmailNode $n, self $g) => $g->renderEmail($n),

			// HTML passthrough nodes
			Nodes\HtmlTagNode::class => fn(Nodes\HtmlTagNode $n, self $g) => $g->renderHtmlTag($n),
			Nodes\HtmlElementNode::class => fn(Nodes\HtmlElementNode $n, self $g) => $g->renderHtmlTag(new Nodes\HtmlTagNode(
				$n->name, $n->attributes,
			))
				. $g->renderNodes($n->content->children)
				. '</' . ($n->closingTag->name ?? $n->name) . '>',
			Nodes\HtmlCommentNode::class => fn(Nodes\HtmlCommentNode $n) => '<!-- ' . trim($n->text) . ' -->',

			// Directive nodes
			Nodes\DirectiveNode::class => fn(Nodes\DirectiveNode $n) => '{{' . $n->text . '}}',

			// Definition nodes (output empty string - handled in document)
			Nodes\ImageDefinitionNode::class => fn() => '',
			Nodes\LinkDefinitionNode::class => fn() => '',
			Nodes\CommentNode::class => fn() => '',
		];
		$this->reader = Markdown::gfm();
	}


	/**
	 * Generate Markdown from AST.
	 */
	public function render(Nodes\DocumentNode $document): string
	{
		$this->linkReferences = [];
		$this->referenceCounter = 0;

		$content = $this->renderNode($document);

		// Append link references at the end if using reference style
		if ($this->linkReferences) {
			$content .= "\n";
			foreach ($this->linkReferences as $ref => $data) {
				$title = $data['title'] ? ' "' . self::escapeTitle($data['title']) . '"' : '';
				$content .= "\n[" . $ref . ']: ' . ($data['url'] === '' ? '<>' : self::escapeUrl($data['url'])) . $title;
			}
		}

		return rtrim($content) . "\n";
	}


	/**
	 * Generate inline content as string.
	 * @param array<Node> $content
	 */
	public function renderNodes(array $content): string
	{
		$result = [];
		foreach ($content as $child) {
			$result[] = $this->renderNode($child);
		}
		return implode('', $result);
	}


	/**
	 * Joins blocks, each ending with its own newline, with a blank line between them.
	 * @param array<Node> $content
	 */
	public function renderBlockContent(array $content): string
	{
		$result = [];
		foreach ($content as $child) {
			$generated = $this->renderNode($child);
			if ($generated !== '') {
				$result[] = $generated;
			}
		}
		return implode("\n", $result);
	}


	private function renderDocument(Nodes\DocumentNode $node): string
	{
		return $this->renderBlockContent($node->content->children);
	}


	private function renderText(Nodes\TextNode $node): string
	{
		return $this->escapeSpecialChars
			? self::escapeText($node->text)
			: $node->text;
	}


	/********************* block node generators ****************d*g**/


	private function renderParagraph(Nodes\ParagraphNode $node): string
	{
		$content = $this->renderNodes($node->content->children);
		if ($this->escapeSpecialChars) {
			$content = $this->ensureParagraph($content);
		}

		return $content === '' ? '' : $content . "\n";
	}


	/**
	 * Text that Markdown reads as a paragraph. A line that would start a block is escaped;
	 * raw HTML cannot be, so its line continues the previous one, or as the first line goes on
	 * with the next one. The first line loses its indentation, which would make it code. Where
	 * the text still reads as a block, as after a first line of raw HTML that starts one, it stays
	 * as it was.
	 */
	private function ensureParagraph(string $text): string
	{
		$text = ltrim($text, " \t");
		if (!$this->reader->readsAsBlock($text)) {
			return $text;
		}

		$joined = preg_match('~\A<[^\n]*~', $text, $m) && $this->reader->readsAsBlock($m[0])
			? preg_replace('~\n~', ' ', $text, 1)
			: $text;

		$res = '';
		foreach (explode("\n", $joined) as $i => $line) {
			$prefix = $i > 0 ? $res . "\n" : '';
			if (!$this->reader->readsAsBlock($prefix . $line)) {
				$res = $prefix . $line;
				continue;
			}

			// text has its < escaped, so a line starting with one is raw HTML
			$escaped = str_starts_with(ltrim($line), '<') ? null : self::escapeBlockStart($line);
			$res = $escaped === null && $i > 0 ? $res . ' ' . $line : $prefix . ($escaped ?? $line);
		}

		return $this->reader->readsAsBlock($res) ? $text : $res;
	}


	private function renderHeading(Nodes\HeadingNode $node): string
	{
		$content = $this->renderNodes($node->content->children);

		if ($this->headingStyle === HeadingStyle::Setext && $node->level <= 2) {
			$underline = $node->level === 1 ? '=' : '-';
			$len = mb_strlen($content);
			return $content . "\n" . str_repeat($underline, max(3, $len)) . "\n";
		}

		// an ATX heading is one line, and hashes after a space at its end would close it
		$content = Regexp::replace(Regexp::replace($content, '~[ \t]*\n~', ' '), '~(^|[ \t])(\#+)$~', '$1\\\$2');
		return str_repeat('#', $node->level) . ' ' . $content . "\n";
	}


	private function renderBlockQuote(Nodes\BlockQuoteNode $node): string
	{
		$content = $this->renderBlockContent($node->content->children);
		return self::prefixLines(rtrim($content), '> ') . "\n";
	}


	/** A blank line would end the block in Markdown, and a block has to start with a tag. */
	private function renderHtmlBlock(Nodes\HtmlBlockNode $node): string
	{
		return str_starts_with(ltrim($node->code), '<') && !preg_match('~\n[ \t]*\n~', $node->code)
			? $node->code . "\n"
			: $this->renderVerbatimBlock($node);
	}


	private function renderVerbatimBlock(Nodes\VerbatimBlockNode $node): string
	{
		// html, text and texysource blocks don't translate well to Markdown, they fall back to a fenced block
		if (!$node instanceof Nodes\CodeBlockNode && !$node instanceof Nodes\PreformattedNode) {
			return $this->renderFencedBlock($node->code, $node instanceof Nodes\HtmlBlockNode ? 'html' : '');
		}

		// a pre block holds HTML; a fenced block shows only its text
		$content = $node instanceof Nodes\PreformattedNode
			? $node->content->getPlainText()
			: $node->code;

		if ($this->codeBlockStyle === CodeBlockStyle::Indented) {
			$lines = explode("\n", $content);
			return implode("\n", array_map(fn($l) => '    ' . $l, $lines)) . "\n";
		}

		return $this->renderFencedBlock($content, $node instanceof Nodes\CodeBlockNode ? $node->language ?? '' : '');
	}


	/**
	 * Fenced code block. Its fence is longer than any run of the fence character inside, which
	 * would close it otherwise.
	 */
	private function renderFencedBlock(string $code, string $language): string
	{
		$char = $this->codeFence[0];
		$runs = Regexp::matchAll($code, '/' . Regexp::quote($char) . '+/');
		$fence = str_repeat($char, max([strlen($this->codeFence), ...array_map(fn($m) => strlen($m[0] ?? '') + 1, $runs)]));
		return $fence . $language . "\n" . ($code === '' ? '' : $code . "\n") . $fence . "\n";
	}


	private function renderDiv(Nodes\DivNode $node): string
	{
		// a div is transparent in Markdown - just output children
		return $this->renderBlockContent($node->content->children);
	}


	private function renderHorizontalRule(Nodes\HorizontalRuleNode $node): string
	{
		return $this->horizontalRule . "\n";
	}


	private function renderFigure(Nodes\FigureNode $node): string
	{
		$image = $this->renderNode($node->image);

		if ($node->caption === null) {
			return $image . "\n";
		}

		$caption = $this->renderNodes($node->caption->children);
		return $image . "\n\n*" . trim($caption) . "*\n";
	}


	/********************* list node generators ****************d*g**/


	private function renderList(Nodes\ListNode $node): string
	{
		$loose = $this->isLoose($node);
		$items = $this->renderItems($node, ['number' => $node->start ?? 1, 'loose' => $loose]);
		return implode($loose ? "\n\n" : "\n", $items) . "\n";
	}


	/**
	 * Renders the items of a list; an item learns from $item how it is rendered.
	 * @param  array{number: int, loose: bool}  $item
	 * @return list<string>
	 */
	private function renderItems(
		Nodes\ListNode|Nodes\DefinitionListNode $list,
		array $item = ['number' => 1, 'loose' => false],
	): array
	{
		$outer = [$this->list, $this->item];
		[$this->list, $this->item] = [$list, $item];
		try {
			$res = [];
			foreach ($list->items as $child) {
				$res[] = $this->renderNode($child);
				$this->item['number']++;
			}

			return $res;

		} finally {
			[$this->list, $this->item] = $outer;
		}
	}


	/**
	 * Markdown wraps the paragraphs of an item all or none. An item of a single paragraph
	 * reads alike in both, so a list keeping the first one unwrapped is loose only when it has
	 * more of them.
	 */
	private function isLoose(Nodes\ListNode $node): bool
	{
		return match ($node->paragraphs) {
			Nodes\ItemParagraphs::Wrapped => true,
			Nodes\ItemParagraphs::Unwrapped => false,
			Nodes\ItemParagraphs::FirstUnwrapped => array_any($node->items, fn($item) => count($item->content->children) > 1),
		};
	}


	private function renderListItem(Nodes\ListItemNode $node): string
	{
		if ($this->list instanceof Nodes\DefinitionListNode) {
			return $this->renderDefinitionItem($node);
		}

		$marker = $this->list?->type->isOrdered()
			? $this->item['number'] . $this->orderedListDelimiter . ' '
			: $this->unorderedListMarker . ' ';
		return $marker . $this->renderListItemContent($node, strlen($marker), $this->item['loose']);
	}


	private function renderListItemContent(Nodes\ListItemNode $node, int $markerWidth, bool $loose = false): string
	{
		$children = $node->content->children;
		$task = match ($node->checked) {
			true => '[x] ',
			false => '[ ] ',
			null => '',
		};

		// Simple case: single paragraph without nested blocks
		if (count($children) === 1 && $children[0] instanceof Nodes\ParagraphNode) {
			return $task . trim($this->renderParagraph($children[0]));
		}

		// Complex case: multiple blocks or nested lists
		$lines = [];
		$first = true;
		foreach ($children as $child) {
			$content = $this->renderNode($child);
			if ($first) {
				$lines[] = rtrim($content);
				$first = false;
			} else {
				// Indent continuation lines
				$indented = self::indent(rtrim($content), $markerWidth);
				$lines[] = $indented;
			}
		}

		return $task . implode($loose ? "\n\n" : "\n", $lines);
	}


	/**
	 * GFM has no definition list, so it is written in HTML. A blank line ends an HTML block,
	 * which lets the content between the tags be read as Markdown.
	 */
	private function renderDefinitionList(Nodes\DefinitionListNode $node): string
	{
		return "<dl>\n" . implode('', $this->renderItems($node)) . "</dl>\n";
	}


	private function renderDefinitionItem(Nodes\ListItemNode $node): string
	{
		$content = $node->term
			? $this->renderParagraph(new Nodes\ParagraphNode($node->content))
			: $this->renderBlockContent($node->content->children);
		$tag = $node->term ? 'dt' : 'dd';
		return "<$tag>\n\n" . $content . "\n</$tag>\n";
	}


	/********************* table node generators ****************d*g**/


	private function renderTable(Nodes\TableNode $node): string
	{
		// GFM tables express neither cell spans nor captions - fall back to an HTML table
		if ($node->caption !== null) {
			return $this->renderHtmlTable($node);
		}

		foreach ($node->rows as $row) {
			foreach ($row->cells as $cell) {
				if ($cell->colspan > 1 || $cell->rowspan > 1) {
					return $this->renderHtmlTable($node);
				}
			}
		}

		$rows = [];
		$headerDone = false;
		$columnAligns = array_map(fn($cell) => $cell->modifier?->hAlign, $node->rows[0]->cells ?? []);

		foreach ($node->rows as $row) {
			$rows[] = $this->renderNode($row);

			// Add separator after header row
			if ($row->header && !$headerDone) {
				$sep = [];
				foreach ($row->cells as $i => $_) {
					$sep[] = self::tableAlignmentSeparator($columnAligns[$i] ?? null);
				}
				$rows[] = '| ' . implode(' | ', $sep) . ' |';
				$headerDone = true;
			}
		}

		// GFM requires a header row; a table without one gets an empty header, so no data row is promoted to it
		if (!$headerDone && $rows) {
			$count = count($node->rows[0]->cells);
			array_unshift(
				$rows,
				'| ' . implode(' | ', array_fill(0, $count, '')) . ' |',
				'| ' . implode(' | ', array_fill(0, $count, '---')) . ' |',
			);
		}

		return implode("\n", $rows) . "\n";
	}


	/**
	 * HTML fallback for tables with colspan/rowspan. Markdown inside a block
	 * HTML island is not processed, so cells carry plain text content.
	 */
	private function renderHtmlTable(Nodes\TableNode $node): string
	{
		$s = "<table>\n";
		if ($node->caption !== null) {
			$s .= '<caption>'
				. htmlspecialchars(trim($node->caption->getPlainText()), ENT_QUOTES, 'UTF-8')
				. "</caption>\n";
		}

		foreach ($node->rows as $row) {
			$s .= '<tr>';
			foreach ($row->cells as $cell) {
				$tag = $cell->header ? 'th' : 'td';
				$s .= '<' . $tag
					. ($cell->colspan > 1 ? ' colspan="' . $cell->colspan . '"' : '')
					. ($cell->rowspan > 1 ? ' rowspan="' . $cell->rowspan . '"' : '')
					. '>'
					. htmlspecialchars(trim($cell->content->getPlainText()), ENT_QUOTES, 'UTF-8')
					. '</' . $tag . '>';
			}
			$s .= "</tr>\n";
		}

		return $s . "</table>\n";
	}


	private function renderTableRow(Nodes\TableRowNode $node): string
	{
		$cells = [];
		foreach ($node->cells as $cell) {
			$cells[] = $this->renderNode($cell);
		}
		return '| ' . implode(' | ', $cells) . ' |';
	}


	private function renderTableCell(Nodes\TableCellNode $node): string
	{
		return self::escapeTableCell(
			trim($this->renderNodes($node->content->children)),
		);
	}


	/********************* inline node generators ****************d*g**/


	private function renderImage(Nodes\ImageNode $node): string
	{
		// the title is the attribute of that name, as Texy writes it
		$alt = $node->alt ?? '';
		$title = $node->modifier?->attrs['title'] ?? null;

		// Check URL scheme (security)
		if ($node->url === null || ($this->urlPolicy !== null && !$this->urlPolicy->isImageAllowed($node->url))) {
			return $this->renderText(new Nodes\TextNode($alt)); // Just return alt text without image
		}

		return '![' . self::escapeText($alt) . '](' . self::escapeUrl($node->url)
			. (is_string($title) ? ' "' . self::escapeTitle($title) . '"' : '')
			. ')';
	}


	private function renderLink(Nodes\LinkNode $node): string
	{
		$text = $this->renderNodes($node->content->children);
		$url = $node->url ?? '';
		$title = $node->modifier?->title;

		// Check URL scheme (security)
		if ($this->urlPolicy !== null && !$this->urlPolicy->isLinkAllowed($url)) {
			return $text; // Just return text without link
		}

		$url = Helpers::normalizeWww($url);

		if ($this->linkStyle === LinkStyle::Reference) {
			$ref = $this->addLinkReference($url, $title);
			return '[' . $text . '][' . $ref . ']';
		}

		// Inline style
		$titlePart = $title ? ' "' . self::escapeTitle($title) . '"' : '';
		return '[' . $text . '](' . self::escapeUrl($url) . $titlePart . ')';
	}


	/**
	 * Add link reference and return reference key.
	 */
	private function addLinkReference(string $url, ?string $title): string
	{
		// Check if URL already exists
		foreach ($this->linkReferences as $ref => $data) {
			if ($data['url'] === $url && $data['title'] === $title) {
				return (string) $ref;
			}
		}

		$ref = (string) ++$this->referenceCounter;
		$this->linkReferences[$ref] = ['url' => $url, 'title' => $title];
		return $ref;
	}


	private function renderPhrase(Nodes\PhraseNode $node): string
	{
		$content = $this->renderNodes($node->content->children);

		return match ($node->type) {
			Nodes\PhraseType::Strong => $this->strongMarker . $content . $this->strongMarker,
			Nodes\PhraseType::Emphasis => $this->emphasisMarker . $content . $this->emphasisMarker,
			Nodes\PhraseType::StrongEmphasis => '***' . $content . '***',
			Nodes\PhraseType::Deleted => '~~' . $content . '~~',
			Nodes\PhraseType::Inserted => '<ins>' . $content . '</ins>',
			Nodes\PhraseType::Superscript => '<sup>' . $content . '</sup>',
			Nodes\PhraseType::Subscript => '<sub>' . $content . '</sub>',
			Nodes\PhraseType::Quote => '"' . $content . '"',
			Nodes\PhraseType::Span, Nodes\PhraseType::Abbreviation => $content,
		};
	}


	/********************* autolink node generators ****************d*g**/


	private function renderUrl(Nodes\UrlNode $node): string
	{
		$url = $node->url;

		// Autolink URLs that start with protocol
		if (preg_match('~^https?://~i', $url)) {
			return '<' . $url . '>';
		}

		// www. URLs need full link syntax
		if (strncasecmp($url, 'www.', 4) === 0) {
			$displayUrl = $this->shortenUrls ? Helpers::shortenUrl($url) : $url;
			return '[' . $displayUrl . '](' . Helpers::normalizeWww($url) . ')';
		}

		return $url;
	}


	private function renderEmail(Nodes\EmailNode $node): string
	{
		return '<' . $node->email . '>';
	}


	/********************* HTML passthrough node generators ****************d*g**/


	private function renderHtmlTag(Nodes\HtmlTagNode $node): string
	{
		$tag = '<';
		if ($node->closing) {
			$tag .= '/';
		}
		$tag .= $node->name;

		foreach ($node->attributes as $name => $value) {
			if ($value === true) {
				$tag .= ' ' . $name;
			} elseif (is_string($value)) {
				$tag .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
			}
		}

		if ($node->selfClosing) {
			$tag .= ' /';
		}
		$tag .= '>';

		return $tag;
	}


	protected function renderUnknown(Node $node): string
	{
		return implode('', $this->renderChildrenOf($node));
	}


	/********************* helpers ****************d*g**/


	/**
	 * Escape special Markdown characters in text.
	 * Only escapes characters that could trigger Markdown syntax in inline context.
	 */
	private static function escapeText(string $text): string
	{
		// Escape: \ ` * _ [ ] < > ~ and the & of an entity
		// These are the main characters that trigger inline Markdown syntax, a tilde strikes through in GFM;
		// a backslash escapes only ASCII punctuation, so before anything else it stays as is (Nette\Forms),
		// and an underscore inside a word cannot open or close emphasis
		return preg_replace(
			'/([`*[\]<>~]|&(?=#?[a-zA-Z0-9]+;)|\\\\(?=[!-\/:-@[-`{-~]|$)|(?<![\p{L}\p{N}])_|_(?![\p{L}\p{N}]))/u',
			'\\\$1',
			$text,
		);
	}


	/**
	 * A line written so that it cannot start a block: its first punctuation after the indentation
	 * and the digits of a list number is escaped. Null where it has none there.
	 */
	private static function escapeBlockStart(string $line): ?string
	{
		$escaped = preg_replace('/^[ \t]*\d*\K(?=[!-\/:-@[-`{-~])/', '\\\\', $line, 1, $count);
		return $count > 0 ? $escaped : null;
	}


	/**
	 * Code span. Its content is literal, so instead of escaping it is delimited by a backtick
	 * run longer than any inside; a space keeps a leading or trailing backtick apart from it.
	 */
	private static function codeSpan(string $code): string
	{
		preg_match_all('/`+/', $code, $m);
		$fence = str_repeat('`', max(array_map(strlen(...), $m[0] ?: [''])) + 1);
		$pad = preg_match('/^`|`$|^ .*[^ ].* $/sD', $code) ? ' ' : '';
		return $fence . $pad . $code . $pad . $fence;
	}


	/**
	 * Escape URL for Markdown (spaces, parentheses).
	 */
	private static function escapeUrl(string $url): string
	{
		return str_replace(['(', ')', ' '], ['%28', '%29', '%20'], $url);
	}


	/**
	 * Escape title in link/image: double quotes, and what is read as an escape or an entity.
	 */
	private static function escapeTitle(string $title): string
	{
		return preg_replace('/"|&(?=#?[a-zA-Z0-9]+;)|\\\\(?=[!-\/:-@[-`{-~]|$)/', '\\\\$0', $title);
	}


	/**
	 * Escape pipe character for table cells.
	 */
	private static function escapeTableCell(string $text): string
	{
		return str_replace('|', '\|', $text);
	}


	/**
	 * Indent multi-line content for nested lists/blockquotes.
	 */
	private static function indent(string $content, int $spaces = 4): string
	{
		$indent = str_repeat(' ', $spaces);
		$lines = explode("\n", $content);
		return implode("\n", array_map(fn($line) => $line !== '' ? $indent . $line : '', $lines));
	}


	/**
	 * Prefix each line with a string (e.g., '> ' for blockquotes).
	 */
	private static function prefixLines(string $content, string $prefix): string
	{
		$lines = explode("\n", $content);
		return implode("\n", array_map(fn($line) => $prefix . $line, $lines));
	}


	/**
	 * Generate GFM table alignment separator based on modifier.
	 */
	private static function tableAlignmentSeparator(?string $hAlign): string
	{
		return match ($hAlign) {
			'left' => ':---',
			'right' => '---:',
			'center' => ':---:',
			default => '---',
		};
	}
}
