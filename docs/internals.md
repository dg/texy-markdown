# Internals

How the parser is put together, and the rules that are not visible from any single
file. For the API see [usage.md](usage.md).

## Two phases, and why

`Texy\Parsing\Engine` normally parses inline content as it goes: a block handler calls
`$context->parseInline()` on the text it has read. Markdown cannot do that, because a
link reference definition may sit anywhere in the document, including after the
paragraph that uses it. Whether `[foo][bar]` is one link, two, or plain text is not
knowable until the last line has been read.

So the phases are cut apart:

1. **Block phase.** Builds the block structure. Every construct that carries inline
   content — paragraphs, headings, table cells — emits a `Parsing\DeferredNode` holding
   the raw text instead of parsing it.
2. **Syntax passes** over the whole document, still inside `parse()`, in order:
   `LinkDefinitionPass` (collects the definitions), `TaskListPass`, `InlineParsePass`
   (the inline phase), `EmphasisPass`, `ImagePass`.

They all finish recognizing the source, so `parse()` returns the syntax tree, the same
state `Texy::parse()` does. `transform()` then applies the meaning: `TagFilterPass`,
the common passes of `Texy\Passes\DocumentPasses` (the HTML policy, heading shift and IDs), the
consumer's passes and typography. The GFM task list marker is
recognized in `parse()`, not in `transform()`, because it is block-level syntax that
takes precedence over inline parsing: `[x]` stays a marker even where a definition
would make it a link.

`ParseContext` enforces the split mechanically: in the block phase its `parseInline()` does not
parse, a closure given to `Engine::parse()` wraps the text in a `DeferredNode`, at every level
of nesting.

What this buys is the whole reference story. By the time a bracket is matched, every
definition is known, so an unresolvable reference simply **refuses the match** — the
bracket becomes literal text and the patterns behind it get their turn at the same
position. There is no unresolved-link node and no degradation pass.

### Nodes that must not survive

`DeferredNode` and `DelimiterNode` are scaffolding; the parse-time `Parsing\PendingImageNode` is
too, because an image's alt text is inline content while it is being parsed and plain
text once it is not. None of the three may appear in the AST `parse()` returns
(guarded by `nodes-parse-time-only.phpt`).

## BlockParser precomputes its matches

Unlike `InlineParser`, which refreshes a pattern's candidate as the position moves,
`BlockParser` matches every pattern once up front and walks the sorted list. **A block
handler that returns `null` therefore also destroys every match inside the span it
rejected.**

This is not a detail. A setext heading that matched a greedy run of lines and rejected
it when one turned out to start a block would, on `---` / `Foo` / `---` / `Bar` /
`---`, get a first match spanning the document, and rejecting it would lose both
headings that are really there. So the conditions live in the pattern, as per-line
lookaheads over the shared `Parsing\Patterns::BlockStarts` fragment, and the handler
has no rejection path at all.

Two consequences worth knowing before touching patterns:

- `BlockStarts` has several consumers — the setext run, the block quote and list lazy
  tests, the table header and row tests, and `Parsing\LineState`. It must include
  HTML block types 1–6 and exclude type 7, and it must **not** include indented code,
  because a four-space line continues a paragraph.
- Anything embedded in a lookahead that is evaluated per line must be possessive. A
  backtracking label repetition blows the PCRE limit, and PCRE reports that as
  "Internal error", which says nothing about where it came from.

## The gap handler owns what cannot interrupt a paragraph

`Modules\ParagraphModule` handles indented code blocks and paragraphs, and it is a line state
machine (`none` / `paragraph` / `code`), not a split on blank lines — an indented code
block contains blank lines of its own. "Cannot interrupt a paragraph" is a *state*
here, which is the only formulation that is actually correct.

It also **continues the paragraph in front of it**. When a block handler refuses its
match, the engine hands the text back and it arrives as a fresh gap that really is that
paragraph's continuation; starting a second paragraph there would tear one in half
around the refused block. The adjacency test is exact: the previous paragraph's
range must end exactly one character before the gap starts.

Link reference definitions are **not** here. They are stripped off the front of a
finished paragraph by `LinkDefinitionPass`, which is where the spec puts them and the
only place that works: whatever splits a gap splits a definition with it.

## The lazy-continuation marker

A line a block quote or a list item accepts as a lazy continuation continues a
paragraph and must not be reinterpreted afterwards — but the container hands its content
back to the block parser as plain text, where that knowledge is gone.

Such lines are prefixed with a reserved byte (`Parsing\Patterns::LazyMarker`, `\x1A`).
Every block pattern opens with `[ ]{0,3}`, so all of them fail on it for free; only the
table header has to exclude it explicitly, because it accepts any character.
`Modules\ParagraphModule` strips it, and `Markdown::normalize()` keeps it out of the input
with the other control characters it removes.

Whether a line *may* be lazily continued is `Parsing\LineState`: the last block the
container has open must be a **paragraph**. "The previous line was not blank" is a
different question and the wrong one — after an indented code block, or inside an open
fence, there is nothing to continue and the container has to end.

## Columns, not bytes

A tab's width depends on the column it sits at, so indentation is measured and removed
in columns (`Parsing\Indentation`). What is left of an indentation comes back as
**spaces**: a tab that survived a strip would be re-measured from the wrong column and
silently lose width. The whitespace after a list marker is counted the same way, and a
block quote marker followed by a tab consumes one column of it, not the whole tab.

## Positions

Every range of the syntax tree points into the source as `Markdown::normalize()` leaves
it, also inside containers (checked over every spec example by
`range-invariants.phpt`). A container parses its stripped content with local offsets and
then maps the result with a `Texy\Parsing\OffsetMap` of its lines; that has to happen
**after** the container is done with the local ranges, because the adjacency test of the
gap handler and the looseness of a list item read them in local coordinates.

The text of a `DeferredNode` has its lines trimmed and its escaped table pipes
unescaped, so it differs from the source; `DeferredNode::$map` leads it back, and an
`OffsetMap` applied to a node composes with the maps it holds, so nesting needs nothing
more. Whatever shortens the deferred text (a definition stripped, a task marker taken)
goes through `cut()`, which keeps the map right.

The one inexact case is a tab that a container partly turns into spaces: the content is
then longer than its source and ranges inside it are approximate.

## What is shared with Texy, and what is not

Almost every construct parses into a core `Texy\Nodes\*` node, which is what makes the
document renderable by every Texy renderer without help. Titles go where Texy's own
syntax puts them: a link's on its modifier, an image's into the attribute of that name,
because the parentheses of a Texy image hold its alternative text, `ImageNode::$alt`.

The package has no nodes of its own. Raw HTML is parsed into `HtmlTagNode`, `HtmlCommentNode`
and `HtmlBlockNode` with the same passes judging them as in Texy (`Parsing\HtmlNodes`); what is
no tag (an instruction, a declaration, CDATA) is `RawTextNode`. The content of `script` and
`style` stays a literal `RawTextNode`, textarea keeps entities only. A tag crossing blocks stays
two unpaired tags in the tree and is paired by the well-forming of the output.

A list says in `ListNode::$paragraphs` whether its items wrap their paragraphs: `Unwrapped` for
a tight one, `Wrapped` for a loose one. A task is `ListItemNode::$checked`, not a tag, so the
policy cannot remove its checkbox.

## Short input that must stay cheap

The parser reads untrusted text, so no construct may cost more than its length suggests
(`pathological.phpt` holds the inputs that would). Five places keep it so, and each looks
like something to simplify:

- **A refused link is searched again from its next character, which lies inside its text.**
  Parsing that text again on every level doubles the work with each level of brackets, so a
  reference without a definition is refused before its text is read, and `ParseState` keeps
  the parsed text of a link by its position. Positions are local to one piece of inline
  content: `InlineParsePass` drops the kept texts before each one, or two paragraphs would
  share nodes.
- **`EmphasisPass` keeps the children and the delimiter runs as linked lists.** Taking a pair
  out of an array rebuilds it, which makes the pairing quadratic.
- **A paragraph grows by `OffsetMap::add()`**, not by building its map again for every line.
- **Repeated groups in patterns are possessive.** A group that gives repetitions back keeps
  a frame for each of them and exhausts the stack of PCRE on a long input, which surfaces as
  a `RegexpException`, or as no match where the package calls PCRE itself. The setext run
  therefore stops before an underline instead of taking it and returning it, and a run no
  underline follows is skipped whole with `(*SKIP)(*FAIL)`, since none of its lines starts a
  heading either. The closing hashes of an ATX heading are cut off in its handler: a lazy
  content followed by them costs the square of the line.
- **Brackets and parentheses do not recurse.** `Patterns::buildNested()` unrolls them to
  `Patterns::MaxNesting` levels; a run nested deeper is not a link.

Blocks nest to `Texy\Parsing\ParseContext::MaxDepth` levels; deeper content is a paragraph.

## The compliance suites

`tests/SpecRenderer.php` renders byte-identically to the reference implementation and
lives in `tests/` deliberately: it is the instrument the suites measure with, not the
package's output path. Both fixtures are committed; nothing is downloaded at test time.
`tools/spec-score.php` is the same measurement in a form you can point at one section
or one example.

`tests/Markdown/fixtures/html-pipeline.expected` is a golden file for the *shipped* HTML path —
not a compliance check, a regression net for the renderer handlers and Texy's
well-forming. Regenerate it with `tools/generate-html-golden.php` and read the diff.
