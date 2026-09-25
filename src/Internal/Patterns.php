<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown\Internal;


/**
 * Shared regular expression fragments.
 *
 * Several of these are consumed by more than one syntax, and a duplicated copy is
 * exactly how a fix that satisfies one spec example breaks another. In particular
 * BlockStarts has several consumers and Attr has two deliberately different variants.
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

	/** A quoted attribute value may hold a newline, so the single-line variant forbids it explicitly. */
	public const AttrValueInline = '(?:[^ \t\n"\'=<>`]+|\'[^\'\n]*\'|"[^"\n]*")';

	/** An attribute of an inline tag; may span lines, which raw inline HTML requires. */
	public const Attr = '(?:[ \t\n]+' . self::AttrName . '(?:[ \t\n]*=[ \t\n]*' . self::AttrValue . ')?)';

	/** An attribute inside an HTML block start condition; a block start must fit on one line. */
	public const AttrInline = '(?:[ \t]+' . self::AttrName . '(?:[ \t]*=[ \t]*' . self::AttrValueInline . ')?)';

	public const OpenTag = '<' . self::TagName . self::Attr . '*[ \t\n]*/?>';
	public const OpenTagInline = '<' . self::TagName . self::AttrInline . '*[ \t]*/?>';
	public const CloseTag = '</' . self::TagName . '[ \t\n]*>';
	public const CloseTagInline = '</' . self::TagName . '[ \t]*>';

	/** A run of nested square brackets, used for link and image labels. */
	public const Bracket = '(?<brk>\[(?:[^\[\]\\\]|\\\.|(?&brk))*\])';

	/** A run of nested parentheses, used inside a link destination. */
	public const Paren = '(?<par>\((?:[^()\\\]|\\\.|(?&par))*\))';

	public const LinkTitle = '(?:"(?:[^"\\\]|\\\.)*"|\'(?:[^\'\\\]|\\\.)*\'|\((?:[^()\\\]|\\\.)*\))';

	/** Characters a backslash may escape. */
	public const Escapable = '[!"\#$%&\'()*+,\-./:;<=>?@\[\\\\\]^_`{|}\~]';

	/**
	 * Marks a line a container accepted as a lazy continuation.
	 *
	 * Such a line continues a paragraph and may not be reinterpreted as the start of
	 * anything, so it gets a byte no block pattern can match: every one of them opens
	 * with `[ ]{0,3}` and this is not a space. Only the setext underline has to say so
	 * explicitly, because it accepts any non-space character. The gap handler strips the
	 * marker again.
	 */
	public const LazyMarker = "\x1A";

	/**
	 * The lines that begin a new block and therefore may not be swallowed as a
	 * paragraph continuation. Consumers: the setext run, the block quote and list lazy
	 * tests, and LineState.
	 *
	 * Deliberately excludes an indented code block (four spaces continue a paragraph)
	 * and HTML block type 7 (which may not interrupt a paragraph either).
	 */
	public const BlockStarts = '[ ]{0,3}(?:'
		. '\#{1,6}(?:[ \t]|$)'                      // ATX heading
		. '|`{3,}|\~{3,}'                           // fenced code
		. '|>'                                      // block quote
		. '|(?:[-*_][ \t]*){3,}$'                   // thematic break
		. '|(?:[-+*]|\d{1,9}[.)])(?:[ \t]|$)'       // list item
		. '|</?(?i:' . self::HtmlBlockTags . ')(?=[ \t\n>]|/>|$)'   // HTML block type 6
		. '|<(?i:script|pre|style|textarea)(?=[ \t\n>]|$)'          // HTML block type 1
		. '|<!--|<\?|<![A-Za-z]|<!\[CDATA\['        // HTML block types 2-5
		. ')';

	/**
	 * The opening of a link reference definition, which likewise cannot interrupt a
	 * paragraph. The label repetition is possessive because this fragment sits inside a
	 * lookahead evaluated at every line: a backtracking version blows the PCRE limit,
	 * which surfaces only as an unhelpful "Internal error".
	 */
	public const DefinitionStart = '[ ]{0,3}\[(?:[^\[\]\n\\\]|\\\.)*+\]:';

	/**
	 * Lines the run underlined by a setext heading may not contain.
	 *
	 * The setext handler has no refusal path on purpose: BlockParser precomputes its
	 * matches once and never refreshes them, so refusing a match also destroys every
	 * match inside the span that was refused. Every condition therefore lives here,
	 * where the regexp engine can simply find the run that does match.
	 */
	public const SetextExcluded = '(?:' . self::BlockStarts . '|' . self::DefinitionStart . ')';


	public function __construct()
	{
		throw new \LogicException('Cannot instantiate static class ' . self::class);
	}


	/** Does the line begin a construct that a paragraph may not swallow? */
	public static function startsBlock(string $line): bool
	{
		return (bool) preg_match('~^' . self::BlockStarts . '~', $line);
	}
}
