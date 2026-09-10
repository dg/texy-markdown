<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Parsing;


/**
 * Shared regular expression fragments.
 *
 * Several of these are consumed by more than one syntax, and a duplicated copy is
 * exactly how a fix that satisfies one spec example breaks another. In particular
 * BlockStarts has several consumers.
 *
 * @internal
 */
final class Patterns
{
	/** Tags that open an HTML block of type 6. Note that `pre` is NOT among them - it is type 1. */
	public const HtmlBlockTags = 'address|article|aside|base|basefont|blockquote|body|caption|center|col|colgroup'
		. '|dd|details|dialog|dir|div|dl|dt|fieldset|figcaption|figure|footer|form|frame|frameset'
		. '|h1|h2|h3|h4|h5|h6|head|header|hr|html|iframe|legend|li|link|main|menu|menuitem|nav|noframes'
		. '|ol|optgroup|option|p|param|search|section|summary|table|tbody|td|tfoot|th|thead|title|tr|track|ul';

	public const TagName = '[A-Za-z][A-Za-z0-9-]*';
	public const AttrName = '[a-zA-Z_:][a-zA-Z0-9_.:-]*';
	public const AttrValue = '(?:[^ \t\n"\'=<>`]+|\'[^\']*\'|"[^"]*")';

	/** An attribute of an inline tag; may span lines, which raw inline HTML requires. */
	public const Attr = '(?:[ \t\n]++' . self::AttrName . '(?:[ \t\n]*+=[ \t\n]*+' . self::AttrValue . ')?+)';

	/** The repetition of attributes is possessive, a tag with thousands of them exhausts the stack of PCRE otherwise. */
	public const OpenTag = '<' . self::TagName . self::Attr . '*+[ \t\n]*+/?>';
	public const CloseTag = '</' . self::TagName . '[ \t\n]*>';

	/** Any construct of raw HTML that is not a block start: tag, comment, instruction, declaration or CDATA. */
	public const RawHtml = self::OpenTag . '|' . self::CloseTag . '|<!-->|<!--->|<!--(?:[^-]++|-(?!->))*+-->'
		. '|<\?[\s\S]*?\?>|<![A-Za-z][^>]*>|<!\[CDATA\[[\s\S]*?\]\]>';

	/** Brackets and parentheses nest at most this deep; the specification allows a limit for the parentheses of a destination only. */
	public const MaxNesting = 32;

	public const LinkTitle = '(?:"(?:[^"\\\]++|\\\.)*+"|\'(?:[^\'\\\]++|\\\.)*+\'|\((?:[^()\\\]++|\\\.)*+\))';

	/** Characters a backslash may escape. */
	public const Escapable = '[!"\#$%&\'()*+,\-./:;<=>?@\[\\\\\]^_`{|}\~]';

	/**
	 * The lines that begin a new block and therefore may not be swallowed as a
	 * paragraph continuation. Consumers: the block quote and list lazy tests.
	 *
	 * Deliberately excludes an indented code block (four spaces continue a paragraph)
	 * and HTML block type 7.
	 */
	public const BlockStarts = '[ ]{0,3}(?:'
		. '\#{1,6}(?:[ \t]|$)'                      // ATX heading
		. '|`{3,}|\~{3,}'                           // fenced code
		. '|>'                                      // block quote
		. '|(?:[-*_][ \t]*+){3,}+$'                 // thematic break
		. '|(?:[-+*]|[0-9]{1,9}[.)])(?:[ \t]|$)'    // list item
		. '|</?(?i:' . self::HtmlBlockTags . ')(?=[ \t\n>]|/>|$)'   // HTML block type 6
		. '|<(?i:script|pre|style|textarea)(?=[ \t\n>]|$)'          // HTML block type 1
		. '|<!--|<\?|<![A-Za-z]|<!\[CDATA\['        // HTML block types 2-5
		. ')';

	/**
	 * A thematic break, the whole line; its character is the first group. The repetition is
	 * possessive, a line of thousands of the characters exhausts the stack of PCRE otherwise.
	 */
	public const ThematicBreak = '[ ]{0,3}([-*_])(?:[ \t]*+\1){2,}+[ \t]*$';


	public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . self::class);
	}


	/**
	 * A balanced run of brackets or parentheses, the escaped $open and $close. It is unrolled to
	 * MaxNesting levels instead of recursing: a run of thousands exhausts the stack of PCRE otherwise.
	 */
	public static function buildNested(string $open, string $close): string
	{
		$pattern = '';
		for ($i = 0; $i < self::MaxNesting; $i++) {
			$pattern = $open . '(?:[^' . $open . $close . '\\\]++|\\\.' . ($pattern === '' ? '' : '|' . $pattern) . ')*+' . $close;
		}

		return $pattern;
	}


	/** Does the line begin a construct that a paragraph may not swallow? */
	public static function startsBlock(string $line): bool
	{
		return (bool) preg_match('~^' . self::BlockStarts . '~', $line);
	}
}
