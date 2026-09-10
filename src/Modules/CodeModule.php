<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Modules;

use Texy;
use Texy\Markdown\Parsing\Escaping;
use Texy\Markdown\Parsing\Indentation;
use Texy\Markdown\Parsing\Lines;
use Texy\Markdown\Syntax;
use Texy\Markdown\Syntax as MdSyntax;
use Texy\Nodes;
use Texy\Parsing\OffsetMap;
use Texy\Parsing\ParseContext;
use Texy\Parsing\Registry;
use Texy\Range;
use function strlen;


/**
 * ```` ```lang ... ``` ```` and its tilde variant.
 *
 * `` `code` ``, with any number of backticks.
 *
 * @internal
 */
final class CodeModule extends Texy\Module
{
	public function register(Registry $registry): void
	{
		$registry->addBlock(
			Syntax::CodeFenced,
			'~^([ ]{0,3})(`{3,}|\~{3,})[ \t]*([^\n]*)$~',
			$this->parseFenced(...),
		);

		$registry->addInline(
			MdSyntax::CodeSpan,
			'~(?<!`)(`+)(?!`)([\s\S]*?)(?<!`)\1(?!`)~',
			$this->parseSpan(...),
		);
	}


	/**
	 * @param  array{string, string, string, string}  $m
	 * @param  array{int, int, int, int}  $offsets
	 */
	private function parseFenced(ParseContext $ctx, array $m, array $offsets): ?Nodes\BlockNode
	{
		if ($m[2][0] === '`' && str_contains($m[3], '`')) {
			return null; // a backtick fence must not have a backtick in its info string
		}

		$parser = $ctx->getBlockParser();
		$indent = strlen($m[1]);
		$closing = '~^[ ]{0,3}' . preg_quote($m[2][0], '~') . '{' . strlen($m[2]) . ',}[ \t]*$~';
		$lines = [];
		$end = $offsets[0] + strlen($m[0]);

		while (($line = Lines::next($parser, $lineOffset)) !== null) {
			$end = $lineOffset + strlen($line);
			if (preg_match($closing, $line)) {
				break;
			}

			$lines[] = Indentation::stripAt($line, $lineOffset, $indent);
		}

		$info = trim(Escaping::unescape($m[3]));
		return new Nodes\CodeBlockNode(
			implode("\n", array_column($lines, 'content')),
			$info === '' ? null : preg_split('~\s~', $info)[0],
			range: new Range($offsets[0], $end - $offsets[0]),
			codeMap: OffsetMap::fromLines($lines),
		);
	}


	/**
	 * @param  array{string, string, string}  $m
	 * @param  array{int, int, int}  $offsets
	 */
	private function parseSpan(ParseContext $ctx, array $m, array $offsets): Nodes\InlineCodeNode
	{
		$code = str_replace("\n", ' ', $m[2]);
		if (str_starts_with($code, ' ') && str_ends_with($code, ' ') && trim($code) !== '') {
			$code = substr($code, 1, -1);
		}

		return new Nodes\InlineCodeNode($code, range: new Range($offsets[0], strlen($m[0])));
	}
}
