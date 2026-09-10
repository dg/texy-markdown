<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown;

use JetBrains\PhpStorm\Language;
use Texy\HtmlPolicy;
use Texy\Nodes;
use Texy\Output\Html\Config as HtmlConfig;
use Texy\Output\Html\Renderer as HtmlRenderer;
use Texy\Parsing\ParseContext;
use Texy\Stage;
use Texy\Texy;
use Texy\UrlPolicy;


/**
 * A CommonMark parser that produces a standard Texy AST.
 *
 * Parsing runs in two phases on purpose: the block phase builds the block structure,
 * a pass collects link reference definitions from it, and only then does the inline
 * phase run as a pass over the whole document. A definition may sit anywhere in the
 * document, so nothing inline can be decided before the block phase has finished.
 *
 * An instance is reusable but not reentrant: parse() refuses to run inside itself.
 */
final class Markdown
{
	/** @var array<string, bool>  syntax name => enabled */
	public array $allowed = [];

	private Registry $registry;
	private ParseState $state;

	/** @var array<string, list<\Closure(Nodes\DocumentNode): void>>  stage name => passes */
	private array $passes = [];

	private bool $parsing = false;


	public function __construct()
	{
		$this->registry = new Registry;
		$this->state = new ParseState;

		foreach ($this->createSyntaxes() as $syntax) {
			$syntax->register();
		}

		foreach (self::defaultSyntaxes() as $name) {
			$this->allowed[$name] = true;
		}
	}


	/**
	 * Converts Markdown to HTML.
	 *
	 * Pass a configured Texy to get its typography, link roots, HTML policy and
	 * everything else it governs; without one the output uses Texy's defaults.
	 */
	public function toHtml(string $text, ?Texy $texy = null): string
	{
		$document = $this->parse($text);
		$this->transform($document, $texy);
		return $this->createHtmlRenderer($texy)->render($document);
	}


	/**
	 * An HTML renderer that knows this package's node types, for rendering documents
	 * repeatedly or outside toHtml(). The given Texy is not modified.
	 */
	public function createHtmlRenderer(?Texy $texy = null): HtmlRenderer
	{
		$renderer = $texy?->createHtmlRenderer() ?? self::createDefaultHtmlRenderer();
		Output\HtmlRenderers::install($renderer);
		return $renderer;
	}


	private static function createDefaultHtmlRenderer(): HtmlRenderer
	{
		$urlPolicy = new UrlPolicy;
		return new HtmlRenderer(new HtmlConfig, new HtmlPolicy($urlPolicy), $urlPolicy);
	}


	/**
	 * Parses Markdown into the syntax tree, a Texy AST whose nodes record where they
	 * are written. Inline content is parsed already; see transform() for the rest.
	 */
	public function parse(string $text): Nodes\DocumentNode
	{
		if ($this->parsing) {
			throw new \LogicException(
				'Markdown::parse() is not reentrant. It runs on one mutable state, so a nested '
				. "call would take the outer document's link reference definitions with it.",
			);
		}

		$this->parsing = true;
		try {
			$this->state->reset($this->allowed);
			$document = $this->registry->parseBlocks(self::normalize($text), $this->allowed);

			foreach ($this->createSyntaxPasses($this->registry->createInlineContext($this->allowed)) as $pass) {
				$pass($document);
			}

			$this->runPasses($document, Stage::Syntax);
			return $document;

		} finally {
			$this->parsing = false;
		}
	}


	/**
	 * Turns the syntax tree into the document, in place: runs the consumer's passes
	 * and, when a Texy is given, its typography. A document is transformed only once.
	 */
	public function transform(Nodes\DocumentNode $document, ?Texy $texy = null): void
	{
		if (!empty($document->meta['transformed'])) {
			return;
		}

		$document->meta['transformed'] = true;
		$this->runPasses($document, Stage::Transform);
		$texy?->applyAstTypography($document);
		$this->runPasses($document, Stage::Finalize);
	}


	/**
	 * Registers a pass: fn(Nodes\DocumentNode): void, called with the whole document
	 * after the built-in passes of its stage. The AST may be freely modified there.
	 * A Transform pass (the default) runs in transform(), a Syntax pass already in parse().
	 * @param  callable(Nodes\DocumentNode): void  $pass
	 */
	public function addPass(callable $pass, Stage $stage = Stage::Transform): void
	{
		$this->passes[$stage->name][] = $pass(...);
	}


	private function runPasses(Nodes\DocumentNode $document, Stage $stage): void
	{
		foreach ($this->passes[$stage->name] ?? [] as $pass) {
			$pass($document);
		}
	}


	/**
	 * Registers a block pattern handler; the pattern must be anchored to the start
	 * of a line and the `m` modifier is added automatically.
	 * @param  callable(ParseContext, array<?string>, array<?int>, string): (Nodes\BlockNode|Nodes\ContentNode|null)  $parser
	 */
	public function addBlockSyntax(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		callable $parser,
	): void
	{
		$this->allowed[$name] ??= true;
		$this->registry->addBlock($name, $pattern, $parser(...));
	}


	/**
	 * Registers an inline pattern handler; the pattern must not be anchored.
	 * @param  callable(ParseContext, array<?string>, array<?int>, string): (Nodes\InlineNode|Nodes\ContentNode|null)  $parser
	 */
	public function addLineSyntax(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		callable $parser,
	): void
	{
		$this->allowed[$name] ??= true;
		$this->registry->addLine($name, $pattern, $parser(...));
	}


	/**
	 * Input normalization. Deliberately not Texy::normalize(): that one right-trims
	 * lines, and two trailing spaces are a hard line break here.
	 *
	 * NUL becomes U+FFFD as the spec requires.
	 */
	public static function normalize(string $text): string
	{
		return str_replace(["\r\n", "\r", "\0"], ["\n", "\n", "\u{FFFD}"], $text);
	}


	/**
	 * The order is the collision-resolution order: when two patterns match at the same
	 * offset with the same length, the one registered first wins. Setext headings must
	 * therefore come after lists and thematic breaks (`- Foo` / `---` is a list plus a
	 * break, not a heading), and links after images.
	 * @return list<Internal\SyntaxRegistrar>
	 */
	private function createSyntaxes(): array
	{
		return [
			new Blocks\FencedCode($this->registry),
			new Blocks\AtxHeading($this->registry),
			new Blocks\ThematicBreak($this->registry),
			new Blocks\HtmlBlocks($this->registry),
			new Blocks\BlockQuotes($this->registry),
			new Blocks\Lists($this->registry),
			new Blocks\SetextHeading($this->registry),
			new Blocks\GapParser($this->registry, $this->state),

			new Inlines\CodeSpans($this->registry),
			new Inlines\Autolinks($this->registry),
			new Inlines\RawHtml($this->registry),
			new Inlines\Escapes($this->registry),
			new Inlines\Links($this->registry, $this->state),
			new Inlines\Emphasis($this->registry),
			new Inlines\Entities($this->registry),
		];
	}


	/** @return list<\Closure(Nodes\DocumentNode): void> */
	private function createSyntaxPasses(ParseContext $context): array
	{
		return [
			(new Passes\LinkDefinitionPass($this->state, !empty($this->allowed[Syntax::LinkDefinition])))->process(...),
			(new Passes\InlineParsePass($context))->process(...),
			(new Passes\EmphasisPass)->process(...),
			(new Passes\ImagePass)->process(...),
		];
	}


	/** @return list<string> */
	private static function defaultSyntaxes(): array
	{
		return [
			Syntax::LinkDefinition, Syntax::CodeFenced, Syntax::CodeIndented,
			Syntax::HeadingAtx, Syntax::HeadingSetext, Syntax::ThematicBreak,
			Syntax::HtmlBlock, Syntax::BlockQuote, Syntax::List,
			Syntax::CodeSpan, Syntax::Autolink, Syntax::AutolinkEmail,
			Syntax::HtmlInline, Syntax::Escape, Syntax::EscapedBreak,
			Syntax::HardBreak, Syntax::SoftBreak, Syntax::Image, Syntax::Link,
			Syntax::Delimiter, Syntax::Entity,
		];
	}
}
