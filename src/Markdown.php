<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown;

use JetBrains\PhpStorm\Language;
use Texy;
use Texy\Config;
use Texy\HtmlPolicy;
use Texy\InvalidArgumentException;
use Texy\Markdown\Parsing\DeferredNode;
use Texy\Markdown\Parsing\ParseState;
use Texy\Nodes;
use Texy\Output\Html\Config as HtmlConfig;
use Texy\Output\Html\Renderer as HtmlRenderer;
use Texy\Parsing\Engine;
use Texy\Parsing\ParseContext;
use Texy\Passes\DocumentPasses;
use Texy\Stage;
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
	public array $allowedSyntax = [];

	/** what the document means */
	public readonly Config $config;

	/** how its HTML looks */
	public readonly HtmlConfig $htmlOutput;

	/** HTML security policy: allowed tags, classes and inline styles */
	public readonly HtmlPolicy $htmlPolicy;

	/** URL scheme security policy for links and images */
	public readonly UrlPolicy $urlPolicy;

	private Engine $engine;
	private ParseState $state;
	private bool $parsing = false;


	/**
	 * Objects shared with another instance share their changes as well. The two policies
	 * are a pair: a given HtmlPolicy brings its own UrlPolicy.
	 * @throws InvalidArgumentException  when the two given policies are not a pair
	 */
	public function __construct(
		?Config $config = null,
		?HtmlConfig $htmlOutput = null,
		?HtmlPolicy $htmlPolicy = null,
		?UrlPolicy $urlPolicy = null,
	) {
		if ($htmlPolicy !== null && $urlPolicy !== null && $htmlPolicy->urlPolicy !== $urlPolicy) {
			throw new InvalidArgumentException('The UrlPolicy must be the one the HtmlPolicy uses.');
		}

		$this->config = $config ?? self::createDefaultConfig();
		$this->htmlOutput = $htmlOutput ?? new HtmlConfig;
		$this->urlPolicy = $urlPolicy ?? $htmlPolicy->urlPolicy ?? new UrlPolicy;
		$this->htmlPolicy = $htmlPolicy ?? new HtmlPolicy($this->urlPolicy);
		$this->engine = new Engine('Markdown');
		$this->state = new ParseState;

		foreach (self::defaultSyntaxes() as $name) {
			$this->engine->declare($name);
		}

		foreach (self::gfmSyntaxes() as $name) {
			$this->engine->declare($name, enabled: false);
		}

		foreach ($this->createModules() as $module) {
			$module->register($this->engine);
		}

		$this->allowedSyntax = $this->engine->completeAllowed([]);
		$this->engine->addPass($this->finishGrammar(...), Stage::Syntax);
		$this->engine->addPass($this->filterTags(...));
		DocumentPasses::register($this->engine, $this->config, $this->htmlPolicy);
	}


	private static function createDefaultConfig(): Config
	{
		$config = new Config;
		$config->locale = 'en';
		$config->hyphenation = false;
		return $config;
	}


	/**
	 * CommonMark plus the five GitHub Flavored Markdown extensions. Each of them can be
	 * switched off again through $allowedSyntax.
	 */
	public static function gfm(
		?Config $config = null,
		?HtmlConfig $htmlOutput = null,
		?HtmlPolicy $htmlPolicy = null,
		?UrlPolicy $urlPolicy = null,
	): self
	{
		$markdown = new self($config, $htmlOutput, $htmlPolicy, $urlPolicy);
		foreach (self::gfmSyntaxes() as $name) {
			$markdown->allowedSyntax[$name] = true;
		}

		return $markdown;
	}


	/**
	 * Converts Markdown to HTML.
	 */
	public function toHtml(string $text): string
	{
		return $this->renderHtml($this->transform($this->parse($text)));
	}


	/**
	 * Renders a transformed document to HTML.
	 */
	public function renderHtml(Nodes\DocumentNode $document): string
	{
		return $this->createHtmlRenderer()->render($document);
	}


	/**
	 * An HTML renderer wired to the current configuration, for rendering documents
	 * repeatedly or outside toHtml().
	 */
	public function createHtmlRenderer(): HtmlRenderer
	{
		return new HtmlRenderer($this->htmlOutput, $this->htmlPolicy, $this->urlPolicy);
	}


	/**
	 * Converts Markdown to Texy.
	 */
	public function toTexy(string $text): string
	{
		return (new Output\Texy\Converter)->convert($this->parse($text));
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
			$this->config->validate();
			$this->allowedSyntax = $this->engine->completeAllowed($this->allowedSyntax);
			$this->state->reset();
			return $this->engine->parse(self::normalize($text), $this->allowedSyntax, inlineDeferral: DeferredNode::wrap(...));

		} finally {
			$this->parsing = false;
		}
	}


	/**
	 * Turns the syntax tree into the document, in place: filters the raw HTML GFM
	 * disallows, applies the HTML policy, heading shift, the consumer's passes and typography.
	 * A second call for one document throws.
	 */
	public function transform(Nodes\DocumentNode $document): Nodes\DocumentNode
	{
		$this->config->validate();
		$this->engine->transform($document);
		return $document;
	}


	/**
	 * Would a block syntax take the text, or a part of it, where a paragraph is meant? Only its
	 * pattern decides, since whether its handler refuses the text can depend on the neighbours.
	 * Indented code and link reference definitions have no pattern, they are found between them.
	 */
	public function readsAsBlock(string $text): bool
	{
		$this->allowedSyntax = $this->engine->completeAllowed($this->allowedSyntax);
		return $this->engine->matchesBlockSyntax(self::normalize($text), $this->allowedSyntax);
	}


	/**
	 * Registers a pass: fn(Nodes\DocumentNode): void, called with the whole document
	 * after the built-in passes of its stage. The AST may be freely modified there.
	 * A Transform pass (the default) runs in transform(), a Syntax pass already in parse().
	 * @param  callable(Nodes\DocumentNode): void  $pass
	 */
	public function addPass(callable $pass, Stage $stage = Stage::Transform): void
	{
		$this->engine->addPass($pass(...), $stage);
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
		$this->engine->addBlock($name, $pattern, $parser, application: true);
		$this->allowedSyntax[$name] ??= true;
	}


	/**
	 * Registers an inline pattern handler; the pattern must not be anchored.
	 * @param  callable(ParseContext, array<?string>, array<?int>, string): (Nodes\InlineNode|Nodes\ContentNode|null)  $parser
	 */
	public function addInlineSyntax(
		string $name,
		#[Language('RegExp')]
		string $pattern,
		callable $parser,
	): void
	{
		$this->engine->addInline($name, $pattern, $parser, application: true);
		$this->allowedSyntax[$name] ??= true;
	}


	public function __clone()
	{
		throw new \LogicException('Clone is not supported.');
	}


	/**
	 * Input normalization. Deliberately not Texy::normalize(): that one right-trims
	 * lines, and two trailing spaces are a hard line break here.
	 *
	 * NUL becomes U+FFFD as the spec requires. Other control characters are removed as in Texy,
	 * the lazy continuation marker with them, so that one arriving from the input cannot be
	 * mistaken for one the parser wrote; tab, newline and form feed stay, the spec reads them
	 * as whitespace.
	 * @throws Texy\InvalidInputException  when the text is not valid UTF-8
	 */
	public static function normalize(string $text): string
	{
		if (!preg_match('##u', $text)) {
			throw new Texy\InvalidInputException('Input is not valid UTF-8 text.');
		}

		$text = str_replace(["\r\n", "\r", "\0"], ["\n", "\n", "\u{FFFD}"], $text);
		return Texy\Regexp::replace($text, '~[\x01-\x08\x0B\x0E-\x1F]+~');
	}


	/**
	 * The order is the collision-resolution order: when two patterns match at the same
	 * offset with the same length, the one registered first wins. Headings must therefore
	 * come after lists and thematic breaks (`- Foo` / `---` is a list plus a break, not a
	 * heading), and links after images. GFM syntaxes are always registered and off by default.
	 * @return list<Texy\Module>
	 */
	private function createModules(): array
	{
		return [
			new Modules\CodeModule,
			new Modules\ThematicBreakModule,
			new Modules\HtmlModule,
			new Modules\AutolinkModule,
			new Modules\EscapeModule,
			new Modules\LineBreakModule,
			new Modules\BlockQuoteModule,
			new Modules\ListModule,
			new Modules\HeadingModule,
			new Modules\ParagraphModule,
			new Modules\LinkModule($this->state),
			new Modules\EmphasisModule,
			new Modules\EntityModule,
			new Modules\TableModule,
		];
	}


	private function finishGrammar(Nodes\DocumentNode $document): void
	{
		new Passes\LinkDefinitionPass($this->state, !empty($this->allowedSyntax[Syntax::LinkDefinition]))->process($document);
		new Passes\TaskListPass(!empty($this->allowedSyntax[Syntax::TaskList]))->process($document);
		new Passes\InlineParsePass($this->engine->createParseContext($this->allowedSyntax), $this->state)->process($document);
		(new Passes\EmphasisPass)->process($document);
		(new Passes\ImagePass)->process($document);
	}


	private function filterTags(Nodes\DocumentNode $document): void
	{
		new Passes\TagFilterPass(!empty($this->allowedSyntax[Syntax::TagFilter]))->process($document);
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


	/** @return list<string> */
	private static function gfmSyntaxes(): array
	{
		return [
			Syntax::Table, Syntax::TaskList, Syntax::Strikethrough,
			Syntax::AutolinkExtended, Syntax::TagFilter,
		];
	}
}
