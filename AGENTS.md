# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.

## Documentation

Any distilled, agent-facing documentation for this package - how it works internally
and the rationale behind key design decisions - lives in `docs/`. Consult it before
non-trivial changes; it is the source of truth from which the public manual is
distilled.

## Project Overview

`texy/markdown` gives Texy the Markdown language in both directions: it parses
CommonMark and GitHub Flavored Markdown into a standard **Texy AST**, and it renders a
Texy AST back to Markdown. HTML output is Texy's job - the AST goes through Texy's own
renderer, so a Markdown document gets typography, sanitization and well-forming for free.
`Markdown::toTexy()` prints a Markdown document as Texy source through Texy's printer.

- **PHP Version**: 8.4 - 8.6
- **Package**: `texy/markdown`, namespace `Texy\Markdown`

## Essential Commands

```bash
composer tester                       # or: vendor/bin/tester tests -s
composer phpstan                      # static analysis, level 8

php tools/spec-score.php              # per-section spec score
php tools/spec-score.php -l           # just the failing example numbers
php tools/spec-score.php Links -v     # one section, with a diff per failure
php tools/spec-score.php -e 565 569   # show these examples
php tools/spec-score.php --gfm        # the GFM extension fixture
```

## Development against an unreleased Texy

`texy/texy` 4.0 is not released yet, so `composer.json` resolves it through a **path
repository at `../_texy`** with `symlink: false`, and the CI workflows check
`dg/texy` out as a sibling directory to recreate that layout. **Both must go away
before the 1.0 release**: drop the `repositories` entry and tighten the constraint to
`^4.0`.

## Conventions

- Every file starts with `declare(strict_types=1);`; Nette Coding Standard.
- Tests are Nette Tester `.phpt`. The two spec suites are **data tests** over committed
  fixtures in `tests/Markdown/fixtures/`; nothing is ever downloaded at test time.

## Working in this repo

- **Parsing is two-phase, and that is the whole design.** The block phase builds the
  block structure; `LinkDefinitionPass` then collects link reference definitions, and
  the inline phase runs after it as a pass, still inside `parse()`. A definition may
  sit anywhere in the document, so nothing inline can be decided before the block
  phase has finished. In the block phase `parseInline()` of the context emits a
  `DeferredNode` instead of parsing.
- **`DeferredNode` and `DelimiterNode` are scaffolding** and must never appear in the
  AST `parse()` returns (guarded by `nodes-parse-time-only.phpt`).
- **Every range points into the source**, inside containers too: a container parses its
  stripped content with local offsets and maps the result with an `OffsetMap` of its
  lines, after it is done with the local ranges (`range-invariants.phpt`, see
  `docs/internals.md`).
- **Registration order is collision-resolution order.** When two patterns match at the
  same offset with the same length, the one registered first wins - see the comment on
  `Markdown::createModules()` before reordering anything there.
- **`BlockParser` precomputes its matches once and never refreshes them**, unlike
  `InlineParser`. A block handler that returns `null` therefore loses the overlapping
  matches of its own pattern. Express a block's refusal conditions **in the
  pattern**, not in the handler.
- **Core `Texy\Nodes\*` are the AST of this package too** - it renders in every output
  format for free, the package has no nodes of its own.
- The spec-exact HTML renderer lives in `tests/`, not `src/`: it is a measuring
  instrument for the compliance suite, not the package's output path.
