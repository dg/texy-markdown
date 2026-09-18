<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Modules;

use Texy;
use Texy\Markdown\Parsing\HtmlNodes;
use Texy\Markdown\Parsing\Lines;
use Texy\Markdown\Parsing\Paragraphs;
use Texy\Markdown\Parsing\Patterns;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\OffsetMap;
use Texy\Parsing\ParseContext;
use Texy\Parsing\Registry;
use Texy\Range;
use function strlen;


/**
 * The seven kinds of HTML block. Types 1-5 have their own terminator, 6 and 7 end
 * at a blank line.
 *
 * Raw HTML tags, comments, processing instructions, declarations and CDATA.
 *
 * @internal
 */
final class HtmlModule extends Texy\Module
{
	public function register(Registry $registry): void
	{
		$registry->addBlock(
			Syntax::HtmlBlock,
			'~^[ ]{0,3}(?:'
			. '(?<t1><(?i:script|pre|style|textarea)(?=[ \t\n>]|$))'
			. '|(?<t2><!--)'
			. '|(?<t3><\?)'
			. '|(?<t4><![A-Za-z])'
			. '|(?<t5><!\[CDATA\[)'
			. '|(?<t6></?(?i:' . Patterns::HtmlBlockTags . ')(?=[ \t\n>]|/>|$))'
			// type 7 must fit on one line, unlike an inline tag
			. '|(?<t7>(?:' . Patterns::OpenTagSingleLine . '|' . Patterns::CloseTagSingleLine . ')[ \t]*$)'
			. ')~',
			$this->parseBlock(...),
		);

		$registry->addInline(
			Syntax::HtmlInline,
			'~' . Patterns::RawHtml . '~',
			$this->parseInline(...),
		);
	}


	/**
	 * @param  array{0: string, t1: ?string, t2: ?string, t3: ?string, t4: ?string, t5: ?string, t6: ?string, t7: ?string, ...}  $m
	 * @param  array{0: int, ...}  $offsets
	 */
	private function parseBlock(ParseContext $ctx, array $m, array $offsets): ?Nodes\BlockNode
	{
		// only type 7 may not interrupt a paragraph; the other six may
		if (($m['t7'] ?? null) !== null && Paragraphs::precedingAt($ctx, $offsets[0]) !== null) {
			return null;
		}

		$parser = $ctx->getBlockParser();
		$parser->moveBackward();

		$end = match (true) {
			($m['t1'] ?? '') !== '' => '~</(?i:script|pre|style|textarea)>~',
			($m['t2'] ?? '') !== '' => '~-->~',
			($m['t3'] ?? '') !== '' => '~\?>~',
			($m['t4'] ?? '') !== '' => '~>~',
			($m['t5'] ?? '') !== '' => '~\]\]>~',
			default => null,
		};

		$lines = [];
		$blockEnd = $offsets[0];
		while (($line = Lines::next($parser, $lineOffset)) !== null) {
			if ($end === null && trim($line) === '') {
				break;
			}

			$lines[] = ['content' => $line, 'offset' => $lineOffset];
			$blockEnd = $lineOffset + strlen($line);
			if ($end !== null && preg_match($end, $line)) {
				break;
			}
		}

		$code = rtrim(implode("\n", array_column($lines, 'content')), "\n");
		$map = OffsetMap::fromLines($lines);
		$content = HtmlNodes::parseBlock($code);
		$map->applyTo($content);
		return new Nodes\HtmlBlockNode($code, $content, null, new Range($offsets[0], $blockEnd - $offsets[0]), $map);
	}


	/**
	 * @param  array{string}  $m
	 * @param  array{int}  $offsets
	 */
	private function parseInline(ParseContext $ctx, array $m, array $offsets): Nodes\InlineNode
	{
		return HtmlNodes::create($m[0], $offsets[0]);
	}
}
