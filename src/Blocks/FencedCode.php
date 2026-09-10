<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Blocks;

use Texy\Markdown\Internal\Escaping;
use Texy\Markdown\Internal\Indentation;
use Texy\Markdown\Internal\Lines;
use Texy\Markdown\Internal\SyntaxRegistrar;
use Texy\Markdown\Registry;
use Texy\Markdown\Syntax;
use Texy\Nodes;
use Texy\Parsing\ParseContext;
use Texy\Range;
use function strlen;


/**
 * ```` ```lang ... ``` ```` and its tilde variant.
 *
 * @internal
 */
final class FencedCode implements SyntaxRegistrar
{
	public function __construct(
		private Registry $registry,
	) {
	}


	public function register(): void
	{
		$this->registry->addBlock(
			Syntax::CodeFenced,
			'~^([ ]{0,3})(`{3,}|\~{3,})[ \t]*([^\n]*)$~',
			$this->parse(...),
		);
	}


	/**
	 * @param  array{string, string, string, string}  $m
	 * @param  array{int, int, int, int}  $offsets
	 */
	private function parse(ParseContext $ctx, array $m, array $offsets): ?Nodes\BlockNode
	{
		if ($m[2][0] === '`' && str_contains($m[3], '`')) {
			return null; // a backtick fence must not have a backtick in its info string
		}

		$parser = $ctx->getBlockParser();
		$indent = strlen($m[1]);
		$closing = '~^[ ]{0,3}' . preg_quote($m[2][0], '~') . '{' . strlen($m[2]) . ',}[ \t]*$~';
		$lines = [];

		while (($line = Lines::next($parser)) !== null) {
			if (preg_match($closing, $line)) {
				break;
			}

			$lines[] = Indentation::strip($line, $indent);
		}

		$info = trim(Escaping::unescape($m[3]));
		return new Nodes\CodeBlockNode(
			'code',
			$lines ? implode("\n", $lines) . "\n" : '',
			$info === '' ? null : preg_split('~\s~', $info)[0],
			null,
			new Range($offsets[0], strlen($m[0])),
		);
	}
}
