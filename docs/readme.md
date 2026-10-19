# texy/markdown documentation

Agent-facing documentation for this package: how to use it, what of Markdown it
covers, and how it works underneath.

## Reference

- **[usage.md](usage.md)** — the API: parsing, HTML output, Markdown output,
  conversion to Texy, switching syntaxes off, adding your own, safe mode.
- **[syntax-support.md](syntax-support.md)** — what is supported and what the two
  flavours differ in, plus what "652 of 652" is measured against.

## Internals

- **[internals.md](internals.md)** — the two-phase parse and the handful of
  non-local rules that come out of it, positions inside containers, and how a document
  is converted to Texy. Read this before changing the parser.
