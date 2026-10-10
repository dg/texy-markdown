# Using texy/markdown

## Parsing

```php
$markdown = new Texy\Markdown\Markdown;          // CommonMark
$markdown = Texy\Markdown\Markdown::gfm();       // + the GFM extensions

$document = $markdown->parse($text);             // the syntax tree, Texy\Nodes\DocumentNode
$markdown->transform($document);                 // the document
```

`parse()` returns an ordinary Texy AST, so `$document->find()`, `NodeTraverser` and every
Texy renderer work on it. It is the syntax tree, the same state `Texy::parse()` returns:
inline content is parsed and every node records where it is written. `transform()`
turns it into the document in place: it filters the raw HTML GFM disallows, applies the HTML
policy and the heading settings, runs your passes and typography, and a second call for one
document throws, as does `Texy::transform()` of it: the passes of Texy would read it as Texy,
a lone `##` would become a first-level heading. An instance is reusable but **not
reentrant**: do not call `parse()` from inside a pass that is itself running under
`parse()`.

## HTML

```php
$html = $markdown->toHtml($text);
```

Rendering goes through Texy's own renderer, so the output is well-formed and indented
rather than byte-identical to the CommonMark reference implementation.

`Markdown` has the same configuration objects as `Texy`, of the same classes:

```php
$markdown->config->locale = 'cs';                // typography, headings
$markdown->config->headingTop = 2;
$markdown->htmlOutput->linkBaseUrl = '/docs/';   // the look of HTML
$markdown->htmlPolicy->allowedTags = ['b' => []]; // what raw HTML may hold
$markdown->urlPolicy->linkPattern = '~https?:~A';
```

Unlike Texy, typography defaults to the locale `en` and hyphenation is off. The defaults apply
to the object `Markdown` creates itself; for an application using both languages, hand in the
objects of Texy, which are shared with their changes:

```php
$markdown = new Texy\Markdown\Markdown(config: $texy->config, htmlOutput: $texy->htmlOutput, htmlPolicy: $texy->htmlPolicy);
```

`Markdown::gfm()` takes the same arguments.

For repeated rendering, or to do the steps yourself:

```php
$renderer = $markdown->createHtmlRenderer();
$document = $markdown->parse($text);
$markdown->transform($document);
$html = $renderer->render($document);
```

Typography is a transform over the AST, not something the renderer does. For the raw HTML of a
document see below.

## Markdown

The other direction renders any Texy AST — including one Texy itself parsed — back to
Markdown:

```php
$renderer = new Texy\Markdown\Output\Markdown\Renderer($texy->urlPolicy);   // the policy is optional
$document = $texy->parse($texySource);
$texy->transform($document);
$text = $renderer->render($document);
```

The URL policy is what keeps the schemes it refuses out of the output: a safe mode the
document was parsed in does not filter links and images by itself, so without the policy
a `javascript:` link of the document is printed as a link.

Its formatting is configurable through public properties on the renderer:
`$headingStyle` (`HeadingStyle::Atx` or `Setext`), `$codeBlockStyle` (`CodeBlockStyle::Fenced`
or `Indented`), `$linkStyle` (`LinkStyle::Inline` or `Reference`), `$codeFence`,
`$unorderedListMarker`, `$orderedListDelimiter`, `$emphasisMarker`, `$strongMarker`,
`$horizontalRule`, `$escapeSpecialChars`, `$shortenUrls`. The three enums live beside the
renderer, in `Texy\Markdown\Output\Markdown`.

Unless `$escapeSpecialChars` is off, a paragraph never reads as another block: a line that
a GFM parser would take for one (`Markdown::readsAsBlock()`) has its first punctuation
escaped, `\- foo` or `1\. foo`.

What GFM has no notation for is written as HTML. A definition list keeps a blank line inside
each of its tags, so the Markdown between them is still read; a table with merged cells or a
caption holds plain text in its cells.

## Switching syntaxes on and off

Every construct is a named syntax in `$allowedSyntax`, the same idiom as `Texy::$allowedSyntax`:

```php
$markdown->allowedSyntax[Texy\Markdown\Syntax::Table] = false;
$markdown->allowedSyntax[Texy\Markdown\Syntax::HtmlInline] = false;
```

The names are constants on `Texy\Markdown\Syntax`. The GFM extensions are registered
even in the plain constructor and merely left off, so `gfm()` is exactly "the same
parser with five more syntaxes enabled".

## Untrusted input

```php
Texy\Markdown\Configurator::safeMode($markdown);
$html = $markdown->toHtml($text);
```

Raw HTML stays and goes through the same whitelist as in the safe mode of Texy, a tag the
policy refuses becomes text; comments are not passed on and an image shows only from `http(s)`,
any other renders as its alternative text. Also without safe
mode raw HTML is parsed into tags and judged by `$markdown->htmlPolicy`, which by default knows
the valid elements of HTML: an unknown tag becomes text unless `allowedTags` is `true`.
Instructions, declarations and CDATA are shown as text. The tag filter of GFM is a rule of that
profile, not sanitization.

## Adding your own syntax

Same signatures as Texy's, and the same handler contract — return a node, return a
`ContentNode` to splice a sequence, or return `null` to refuse the match:

```php
$markdown->addInlineSyntax('custom/mention', '~@@([a-z0-9_]+)~i', $handler);
$markdown->addBlockSyntax('custom/alert', '~^:::(warning|info)$~', $handler);
$markdown->addPass(function (Texy\Nodes\DocumentNode $document): void { /* … */ });
```

Collisions are resolved by position — leftmost, then longest, then your syntaxes before the
built-in ones, then **registration order**. Block syntaxes compare no length.

Two things differ from extending Texy, both consequences of the two-phase parse
([internals.md](internals.md)):

- In a **block** handler `$context->parseInline()` only defers the text: the inline phase
  parses it later, as the link reference definitions are not collected yet. The content it
  returns cannot be read as a finished tree before `parse()` ends.
- A block handler should express its refusal conditions **in its pattern** rather than
  returning `null`, wherever it can. `BlockParser` computes its matches once, so a
  refused match keeps the matches of other patterns inside its span, but those of its own
  pattern overlapping it are lost.

Passes registered with `addPass()` run in `transform()` after the built-in ones, so
they see a finished AST. One registered with `Texy\Stage::Syntax` runs at the end of
`parse()` instead, and may only finish recognizing the source.
