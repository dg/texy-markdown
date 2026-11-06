# Using texy/markdown

## Parsing

```php
$markdown = new Texy\Markdown\Markdown;          // CommonMark
$markdown = Texy\Markdown\Markdown::gfm();       // + the GFM extensions

$document = $markdown->parse($text);             // the syntax tree, Texy\Nodes\DocumentNode
$markdown->transform($document, $texy);          // the document; $texy is optional
```

`parse()` returns an ordinary Texy AST, so `NodeHelpers`, `NodeTraverser` and every
Texy renderer work on it. It is the syntax tree, the same state `Texy::parse()` returns:
inline content is parsed and every node records where it is written. `transform()`
turns it into the document in place: it filters the raw HTML GFM disallows, runs your
passes and, given a `Texy`, applies its typography. An instance is reusable but **not
reentrant**: do not call `parse()` from inside a pass that is itself running under
`parse()`.

## HTML

```php
$html = $markdown->toHtml($text);                // Texy's defaults
$html = $markdown->toHtml($text, $texy);         // your Texy's configuration
```

Rendering goes through Texy's own renderer, so the output is well-formed and indented
rather than byte-identical to the CommonMark reference implementation. Passing a `Texy`
brings everything that instance governs: typography, `linkRoot` and `imageRoot`, the
HTML policy, `rel="nofollow"`, line wrapping. The instance is not modified.

For repeated rendering, or to do the steps yourself:

```php
$renderer = $markdown->createHtmlRenderer($texy);
$document = $markdown->parse($text);
$markdown->transform($document, $texy);
$html = $renderer->render($document);
```

Typography is a transform over the AST, not something the renderer does, which is why
`transform()` takes the `Texy` too.

## Markdown

The other direction renders any Texy AST — including one Texy itself parsed — back to
Markdown:

```php
$renderer = new Texy\Markdown\Output\Renderer($texy->urlPolicy);   // the policy is optional
$document = $texy->parse($texySource);
$texy->transform($document);
$text = $renderer->render($document);
```

Its formatting is configurable through public properties on the renderer:
`$headingStyle`, `$codeBlockStyle`, `$codeFence`, `$unorderedListMarker`,
`$orderedListDelimiter`, `$emphasisMarker`, `$strongMarker`, `$linkStyle`,
`$horizontalRule`, `$escapeSpecialChars`, `$shortenUrls`.

Unless `$escapeSpecialChars` is off, a paragraph never reads as another block: a line that
a GFM parser would take for one (`Markdown::readsAsBlock()`) has its first punctuation
escaped, `\- foo` or `1\. foo`.

## Texy

Markdown converts to Texy source:

```php
$texySource = $markdown->toTexy($text);
```

The result is written in Texy's own notation, not in Markdown's: `*em*` becomes
`//em//`, a link `"text":url`, headings are underlined. Text that Texy would read as
syntax is escaped with entities, and what Texy writes no notation for comes out as HTML:
strikethrough as `<del>` (off in Texy by default), an emphasis nested in an emphasis as
`<em>`, a link that the text after it would run into and whose text, target or title
`[text](url)` cannot hold as `<a>`, an HTML block as `/--html`. Read by a default
`Texy`, the result says what the Markdown said, as far as Texy can say it: a list
numbered above 999 or a list item starting empty does not become a list.

## Switching syntaxes on and off

Every construct is a named syntax in `$allowed`, the same idiom as `Texy::$allowed`:

```php
$markdown->allowed[Texy\Markdown\Syntax::Table] = false;
$markdown->allowed[Texy\Markdown\Syntax::HtmlInline] = false;
```

The names are constants on `Texy\Markdown\Syntax`. The GFM extensions are registered
even in the plain constructor and merely left off, so `gfm()` is exactly "the same
parser with five more syntaxes enabled".

## Untrusted input

```php
$texy = new Texy\Texy;
Texy\Markdown\Configurator::safeMode($markdown, $texy);
$html = $markdown->toHtml($text, $texy);
```

This is Texy's own safe mode plus switching raw HTML and images off at the parser. Raw
HTML is kept as literal source here — that is what byte-identical passthrough means — so
there is no tag model to run a whitelist over; text that is never parsed as markup cannot
smuggle any through instead.

Note that CommonMark deliberately says nothing about HTML sanitization. `toHtml()`
without safe mode is spec-correct, not lax by oversight.

## Adding your own syntax

Same signatures as Texy's, and the same handler contract — return a node, return a
`ContentNode` to splice a sequence, or return `null` to refuse the match:

```php
$markdown->addLineSyntax('custom/mention', '~@@([a-z0-9_]+)~i', $handler);
$markdown->addBlockSyntax('custom/alert', '~^:::(warning|info)$~', $handler);
$markdown->addPass(function (Texy\Nodes\DocumentNode $document): void { /* … */ });
```

Collisions are resolved by position — leftmost, then longest, then **registration
order** — and your syntaxes register after all the built-in ones, so at equal position
and length a built-in wins. Keep patterns specific enough that it does not come up.

Two things differ from extending Texy, both consequences of the two-phase parse
([internals.md](internals.md)):

- A **block** handler must not call `$context->parseInline()`. The inline parser it
  would reach has no patterns at that point, on purpose. Emit a
  `Texy\Markdown\Nodes\DeferredNode` instead and the inline phase will parse it.
- A block handler should express its refusal conditions **in its pattern** rather than
  returning `null`, wherever it can. `BlockParser` computes its matches once, so a
  refused match also takes with it every match inside the span it covered.

Passes registered with `addPass()` run in `transform()` after the built-in ones, so
they see a finished AST. One registered with `Texy\Stage::Syntax` runs at the end of
`parse()` instead, and may only finish recognizing the source.
