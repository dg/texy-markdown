Texy Markdown
=============

[![Downloads this Month](https://img.shields.io/packagist/dm/texy/markdown.svg)](https://packagist.org/packages/texy/markdown)
[![Tests](https://github.com/dg/texy-markdown/workflows/Tests/badge.svg?branch=master)](https://github.com/dg/texy-markdown/actions)
[![Latest Stable Version](https://poser.pugx.org/texy/markdown/v/stable)](https://github.com/dg/texy-markdown/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/dg/texy-markdown/blob/master/license.md)

Markdown for [Texy](https://texy.deegee.dev), in both directions. It parses CommonMark
and GitHub Flavored Markdown into a standard **Texy AST**, and it renders a Texy AST
back to Markdown.

The AST is the point. Markdown and Texy become two front ends to one tree, so a
Markdown document goes out through Texy's own renderer and gets typography, HTML
sanitization and well-forming for free - and converting between the two languages is
just a matter of which parser and which renderer you pick.

Install via Composer:

    composer require texy/markdown

Requires PHP 8.4 or higher.


Usage
-----

    $markdown = new Texy\Markdown\Markdown;
    $html = $markdown->toHtml($text);

Typography, link roots and the HTML policy are configured on the instance:

    $markdown->config->locale = 'cs';
    $markdown->htmlOutput->linkBaseUrl = '/docs/';
    $markdown->htmlPolicy->allowedTags = ['b' => []];

GitHub Flavored Markdown adds tables, task lists, strikethrough, extended autolinks
and the disallowed-raw-HTML filter:

    $markdown = Texy\Markdown\Markdown::gfm();

Every construct can be switched off individually:

    $markdown->allowedSyntax[Texy\Markdown\Syntax::Table] = false;

The parsed document is an ordinary Texy AST, so `$document->find()`, `NodeTraverser`,
transform passes and every Texy renderer work on it:

    $document = $markdown->parse($text);

And the other direction, a Texy AST rendered as Markdown:

    $renderer = new Texy\Markdown\Output\Markdown\Renderer;
    $document = $texy->parse($texySource);
    $texy->transform($document);
    $markdownText = $renderer->render($document);


Documentation
-------------

The [docs/](docs/) directory holds the reference for using and extending this package.


[Support Me](https://github.com/sponsors/dg)
--------------------------------------------

Do you like Texy? Are you looking forward to the new features?

[![Buy me a coffee](https://files.nette.org/icons/donation-3.svg)](https://github.com/sponsors/dg)

Thank you!
