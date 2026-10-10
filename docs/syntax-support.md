# What is supported

## CommonMark

All of it: **652 of 652 examples** of the [CommonMark 0.31.2 specification](https://spec.commonmark.org/0.31.2/),
measured against the reference implementation's own output, byte for byte.

The suite is committed as `tests/fixtures/spec-commonmark.json` and runs as an ordinary
test. Nothing is downloaded at test time, so the number is reproducible offline and on
any future version of the parser.

## GitHub Flavored Markdown

All five extensions, **24 of 24 examples** of the GFM specification's extension
sections:

| Extension | Syntax name |
|---|---|
| Tables | `Syntax::Table` |
| Task list items | `Syntax::TaskList` |
| Strikethrough | `Syntax::Strikethrough` |
| Autolinks without pointy brackets | `Syntax::AutolinkExtended` |
| Disallowed raw HTML | `Syntax::TagFilter` |

Enable them all with `Markdown::gfm()`, or any subset through `$allowed`. They are
registered even in the plain constructor and simply left off, so the two flavours share
one engine — and one collision-resolution order, which is the part that could otherwise
drift.

The GFM specification is a fork of the CommonMark one and its CommonMark half is a
version behind. Only the extension sections are tracked here; the rest would contradict
the suite above. `tools/extract-gfm-spec.php` is what produced the fixture, and the
example numbers in it are upstream's, so a failure stays cross-referenceable.

Footnotes are a GitHub feature, not part of the GFM specification, and are not
implemented.

## Two kinds of HTML output

`toHtml()` renders through Texy, which indents, wraps lines and well-forms. That output
is **not** byte-identical to the reference implementation, on purpose — it is the point
of the package. The byte-identical renderer exists, in `tests/`, because that is what a
compliance suite has to compare against; it is an instrument, not an output mode.

If you need HTML that matches another Markdown implementation byte for byte, this
package is the wrong tool.
