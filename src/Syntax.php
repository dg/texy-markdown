<?php declare(strict_types=1);

/**
 * This file is part of the Texy! (https://texy.deegee.dev)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Texy\Markdown;


/**
 * Syntax identifiers for use with Markdown::$allowed[].
 */
final class Syntax
{
	// Block constructs

	/** Link reference definition (`[label]: /url "title"`) */
	public const LinkDefinition = 'link/definition';

	/** Fenced code block (```` ``` ```` or `~~~`) */
	public const CodeFenced = 'block/fenced';

	/** Indented code block (four spaces) */
	public const CodeIndented = 'block/indented';

	/** ATX heading (`### text`) */
	public const HeadingAtx = 'heading/atx';

	/** Setext heading (text underlined with `===` or `---`) */
	public const HeadingSetext = 'heading/setext';

	/** Thematic break (`***`, `---`, `___`) */
	public const ThematicBreak = 'horizline';

	/** Raw HTML block (all seven kinds) */
	public const HtmlBlock = 'html/block';

	/** Block quote (`> text`) */
	public const BlockQuote = 'blockquote';

	/** Bullet or ordered list */
	public const List = 'list';

	// Inline constructs

	/** Code span (`` `code` ``) */
	public const CodeSpan = 'phrase/code';

	/** Autolink (`<https://example.com>`) */
	public const Autolink = 'link/auto';

	/** E-mail autolink (`<user@example.com>`) */
	public const AutolinkEmail = 'link/email';

	/** Raw inline HTML (tags, comments, processing instructions, declarations, CDATA) */
	public const HtmlInline = 'html/inline';

	/** Backslash escape (`\*`) */
	public const Escape = 'escape';

	/** Hard line break written as a backslash before a newline */
	public const EscapedBreak = 'escape/break';

	/** Hard line break written as two spaces before a newline */
	public const HardBreak = 'break/hard';

	/** Soft line break (a bare newline inside a paragraph) */
	public const SoftBreak = 'break/soft';

	/** Image (`![alt](/url)`) */
	public const Image = 'image';

	/** Link (`[text](/url)`, `[text][ref]`, `[ref]`) */
	public const Link = 'link';

	/** Emphasis delimiter run (`*`, `_`); the pairing happens in a pass inside parse() */
	public const Delimiter = 'delimiter';

	/** HTML entity reference (`&amp;`, `&#35;`) */
	public const Entity = 'entity';

	// GFM extensions

	/** GFM table (`| a | b |`) */
	public const Table = 'table';

	/** GFM task list item (`- [ ]`, `- [x]`) */
	public const TaskList = 'list/task';

	/** GFM strikethrough (`~~text~~`) */
	public const Strikethrough = 'phrase/del';

	/** GFM extended autolink (`www.`, bare `http://`, bare e-mail) */
	public const AutolinkExtended = 'link/auto-extended';

	/** GFM disallowed raw HTML filter */
	public const TagFilter = 'html/tagfilter';
}
