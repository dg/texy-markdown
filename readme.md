Texy Markdown
=============

[![Downloads this Month](https://img.shields.io/packagist/dm/texy/markdown.svg)](https://packagist.org/packages/texy/markdown)
[![Tests](https://github.com/dg/texy-markdown/workflows/Tests/badge.svg?branch=master)](https://github.com/dg/texy-markdown/actions)
[![Latest Stable Version](https://poser.pugx.org/texy/markdown/v/stable)](https://github.com/dg/texy-markdown/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/dg/texy-markdown/blob/master/license.md)

Markdown for [Texy](https://texy.deegee.dev). It parses CommonMark into a standard
**Texy AST**.

The AST is the point. Markdown and Texy become two front ends to one tree, so a
Markdown document goes out through Texy's own renderer and gets typography, HTML
sanitization and well-forming for free.

Install via Composer:

    composer require texy/markdown

Requires PHP 8.2 or higher.


Usage
-----

    $markdown = new Texy\Markdown\Markdown;
    $html = $markdown->toHtml($text);

To render with your own Texy configuration - typography, link roots, safe mode:

    $texy = new Texy\Texy;
    $html = $markdown->toHtml($text, $texy);

Every construct can be switched off individually:

    $markdown->allowed[Texy\Markdown\Syntax::HtmlBlock] = false;

The parsed document is an ordinary Texy AST, so `NodeHelpers`, `NodeTraverser`,
transform passes and every Texy renderer work on it:

    $document = $markdown->parse($text);


[Support Me](https://github.com/sponsors/dg)
--------------------------------------------

Do you like Texy? Are you looking forward to the new features?

[![Buy me a coffee](https://files.nette.org/icons/donation-3.svg)](https://github.com/sponsors/dg)

Thank you!
