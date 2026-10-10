<?php declare(strict_types=1);

/**
 * The same Markdown document rendered three ways.
 *
 * Usage: php examples/demo.php [file.md]
 */

use Texy\Markdown\Markdown;
use Texy\Markdown\Output\Markdown\Renderer as MarkdownRenderer;

if (@!include __DIR__ . '/../vendor/autoload.php') {
	die("Install packages using `composer install`\n");
}

$source = isset($argv[1])
	? file_get_contents($argv[1])
	: <<<'MD'
		Sample document
		===============

		A paragraph with *emphasis*, `code`, a [link][home] and an ![image](logo.png "Logo").
		Text with "quotes", an ellipsis... and a dash -- all for the typographer.

		- a tight list
		- with two items

		| left | center | right |
		| :--- | :----: | ----: |
		| 1    | 2      | 3     |

		> A quote with ~~strikethrough~~ and an autolink: www.example.com

		[home]: https://example.com "Home page"
		MD;

$markdown = Markdown::gfm();

echo "=== HTML, the defaults ===\n\n";
echo $markdown->toHtml($source), "\n";

echo "=== HTML, configured: Czech typography ===\n\n";
$czech = Markdown::gfm();
$czech->config->locale = 'cs';
echo $czech->toHtml($source), "\n";

echo "=== back to Markdown, from the syntax tree ===\n\n";
echo (new MarkdownRenderer)->render($markdown->parse($source)), "\n";
