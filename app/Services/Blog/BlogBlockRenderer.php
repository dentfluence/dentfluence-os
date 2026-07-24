<?php

namespace App\Services\Blog;

use App\Models\Marketing\MarketingAsset;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a canonical block-JSON document (see BlogBlockSchema) into safe
 * HTML. This output is the `blog_posts.body_html` cache and, later, the
 * body pushed to WordPress by the publish adapter — so it must stay plain,
 * portable HTML (no app CSS classes beyond simple hooks, no scripts).
 *
 * Contract:
 *  - Pure with respect to input: same document → same HTML (image blocks do
 *    read mkt_assets to resolve asset URLs).
 *  - Unknown block types render NOTHING (graceful skip) so new types can be
 *    introduced later with no data-model change and no breakage here.
 *  - All user-entered text is escaped. The only pass-through-ish input is
 *    paragraph `html` (limited inline markup from the editor), which is run
 *    through an allowlist sanitizer.
 */
class BlogBlockRenderer
{
    /** Inline tags allowed inside paragraph `html` (editor marks only). */
    private const ALLOWED_INLINE_TAGS = '<strong><b><em><i><u><s><a><br><code><sup><sub>';

    /**
     * Block-level allowlist for the single-surface `richtext` document (the
     * Wix/Docs-style editor). Everything not on this list is unwrapped (its text
     * survives) or, for <img>, dropped if its src is unsafe. See sanitizeBlockHtml().
     */
    private const RICHTEXT_TAGS = [
        'p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote',
        'strong', 'b', 'em', 'i', 'u', 's', 'a', 'code', 'pre', 'br',
        'img', 'figure', 'figcaption',
        // 'mark' (highlight, no attributes) and 'span' (text colour — only a
        // vetted color/background-color declaration survives, see
        // allowedTextColorStyle()) so the editor's colour/highlight marks
        // persist into the published HTML instead of being stripped.
        'span', 'mark',
    ];

    /** Block-level tags allowed to carry a vetted text-align style. */
    private const RICHTEXT_ALIGNABLE = ['p', 'h2', 'h3', 'h4', 'blockquote', 'li'];

    /**
     * Render a full body_json document to HTML.
     */
    public function render(array $bodyJson): string
    {
        $html = [];

        foreach (($bodyJson['blocks'] ?? []) as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            $rendered = match ($type) {
                'heading'   => $this->heading($data),
                'paragraph' => $this->paragraph($data),
                'image'     => $this->image($data),
                'quote'     => $this->quote($data),
                'table'     => $this->table($data),
                'cta'       => $this->cta($data),
                'faq'       => $this->faq($data),
                'divider'   => "<hr>\n",
                'list'      => $this->list($data),
                'richtext'  => $this->richtext($data),
                default     => '', // unknown/future type: skip gracefully
            };

            if ($rendered !== '') {
                $html[] = $rendered;
            }
        }

        return implode('', $html);
    }

    /**
     * Flatten a document to plain text (for excerpt generation, reading-time
     * estimation, and SEO analysis in later slices). Skips images/dividers;
     * includes heading, paragraph, quote, table, cta and faq text.
     */
    public function blocksToPlainText(array $bodyJson): string
    {
        $parts = [];

        foreach (($bodyJson['blocks'] ?? []) as $block) {
            if (! is_array($block)) {
                continue;
            }
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            switch ($block['type'] ?? null) {
                case 'heading':
                case 'quote':
                    $parts[] = (string) ($data['text'] ?? '');
                    break;

                case 'paragraph':
                    $text = $data['text'] ?? null;
                    if ($text === null && isset($data['html'])) {
                        $text = strip_tags((string) $data['html']);
                    }
                    $parts[] = (string) $text;
                    break;

                case 'table':
                    foreach (($data['rows'] ?? []) as $row) {
                        if (is_array($row)) {
                            $parts[] = implode(' ', array_map('strval', $row));
                        }
                    }
                    break;

                case 'cta':
                    $parts[] = (string) ($data['label'] ?? '');
                    break;

                case 'list':
                    foreach (($data['items'] ?? []) as $item) {
                        if (is_string($item)) {
                            $parts[] = strip_tags($item);
                        }
                    }
                    break;

                case 'richtext':
                    // One flowing document — strip its tags to plain text for
                    // excerpt/reading-time. Insert spaces where tags used to be so
                    // adjacent block text doesn't run together ("a</p><p>b" -> "a b").
                    if (isset($data['html']) && is_string($data['html'])) {
                        $parts[] = strip_tags(str_replace('<', ' <', $data['html']));
                    }
                    break;

                case 'faq':
                    foreach (($data['items'] ?? []) as $item) {
                        if (is_array($item)) {
                            $parts[] = trim((string) ($item['q'] ?? '') . ' ' . (string) ($item['a'] ??  ''));
                        }
                    }
                    break;
            }
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($parts, fn ($p) => $p !== ''))) ?? '');
    }

    // -----------------------------------------------------------------------
    // Per-type renderers
    // -----------------------------------------------------------------------

    private function heading(array $data): string
    {
        $text = trim((string) ($data['text'] ?? ''));
        if ($text === '') {
            return '';
        }
        // Clamp to h2-h6 in the body: h1 is the post title's job.
        $level = min(6, max(2, (int) ($data['level'] ?? 2)));

        return "<h{$level}>" . e($text) . "</h{$level}>\n";
    }

    private function paragraph(array $data): string
    {
        if (isset($data['html']) && is_string($data['html']) && $data['html'] !== '') {
            $inner = $this->sanitizeInlineHtml($data['html']);
        } else {
            $text = trim((string) ($data['text'] ?? ''));
            if ($text === '') {
                return '';
            }
            // Escaped plain text; single newlines inside a paragraph become <br>.
            $inner = nl2br(e($text), false);
        }

        return $inner === '' ? '' : "<p>{$inner}</p>\n";
    }

    private function image(array $data): string
    {
        $url = $this->resolveImageUrl($data);
        if ($url === null) {
            return '';
        }

        $alt     = e((string) ($data['alt'] ?? ''));
        $caption = trim((string) ($data['caption'] ?? ''));

        $img = '<img src="' . e($url) . '" alt="' . $alt . '">';

        return $caption === ''
            ? "<figure>{$img}</figure>\n"
            : "<figure>{$img}<figcaption>" . e($caption) . "</figcaption></figure>\n";
    }

    private function quote(array $data): string
    {
        $text = trim((string) ($data['text'] ?? ''));
        if ($text === '') {
            return '';
        }
        $cite = trim((string) ($data['cite'] ?? ''));

        $html = '<blockquote><p>' . e($text) . '</p>';
        if ($cite !== '') {
            $html .= '<cite>' . e($cite) . '</cite>';
        }

        return $html . "</blockquote>\n";
    }

    private function table(array $data): string
    {
        $rows = array_values(array_filter(
            (array) ($data['rows'] ?? []),
            'is_array'
        ));
        if ($rows === []) {
            return '';
        }

        // First row = header, remaining = body.
        $header = array_shift($rows);

        $html = "<table>\n<thead><tr>";
        foreach ($header as $cell) {
            $html .= '<th>' . e((string) $cell) . '</th>';
        }
        $html .= "</tr></thead>\n<tbody>";

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . e((string) $cell) . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . "</tbody>\n</table>\n";
    }

    private function cta(array $data): string
    {
        $label = trim((string) ($data['label'] ?? ''));
        $url   = $this->safeUrl((string) ($data['url'] ?? ''));
        if ($label === '' || $url === null) {
            return '';
        }

        // 'button' vs 'link' only differ by a class hook; styling is owned by
        // the render target (our pages / WP theme), keeping the HTML portable.
        $class = ($data['style'] ?? 'button') === 'link' ? 'blog-cta-link' : 'blog-cta-button';

        return '<p class="blog-cta"><a class="' . $class . '" href="' . e($url) . '">' . e($label) . "</a></p>\n";
    }

    private function faq(array $data): string
    {
        $items = array_values(array_filter((array) ($data['items'] ?? []), 'is_array'));
        if ($items === []) {
            return '';
        }

        $html = '';
        foreach ($items as $item) {
            $q = trim((string) ($item['q'] ?? ''));
            $a = trim((string) ($item['a'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            // <details>/<summary>: semantic, JS-free, survives WordPress intact.
            $html .= '<details class="blog-faq-item"><summary>' . e($q) . '</summary><p>'
                   . nl2br(e($a), false) . '</p></details>';
        }

        return $html === '' ? '' : '<section class="blog-faq">' . $html . "</section>\n";
    }

    /**
     * `list` block: bulleted (<ul>) or numbered (<ol>). Each item runs through
     * the SAME inline-html allowlist sanitizer as paragraph blocks, so bold/
     * italic/link marks made in the editor survive; anything else is stripped.
     */
    private function list(array $data): string
    {
        $items = array_values(array_filter((array) ($data['items'] ?? []), 'is_string'));

        $lis = [];
        foreach ($items as $item) {
            $inner = $this->sanitizeInlineHtml($item);
            if ($inner === '') {
                continue;
            }
            $lis[] = '<li>' . $inner . '</li>';
        }
        if ($lis === []) {
            return '';
        }

        $tag = ($data['style'] ?? 'bullet') === 'number' ? 'ol' : 'ul';

        return "<{$tag}>" . implode('', $lis) . "</{$tag}>\n";
    }

    /**
     * `richtext` block: the whole post body as one word-processor document.
     * Ships the editor's own HTML through a strict block-level allowlist
     * (sanitizeBlockHtml) so the stored/published markup stays portable and safe.
     */
    private function richtext(array $data): string
    {
        $html = isset($data['html']) && is_string($data['html']) ? $data['html'] : '';
        if (trim($html) === '') {
            return '';
        }

        $clean = $this->sanitizeBlockHtml($html);

        return $clean === '' ? '' : $clean . "\n";
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Sanitize the single-surface editor's document HTML against a block-level
     * allowlist (RICHTEXT_TAGS). Uses DOMDocument to walk the tree — safer than
     * regex for nested block markup with attributes:
     *   - unknown/disallowed elements are UNWRAPPED (their text is kept)
     *   - all attributes are dropped except a vetted href on <a>, src/alt on
     *     <img>, a `text-align: left|center|right` style on block elements,
     *     and a vetted `color`/`background-color` style on <span> (see
     *     allowedTextColorStyle()) — <mark> carries no attributes at all
     *   - unsafe <img> (javascript:/data:/relative-scheme src) are dropped
     *   - all text is escaped
     * Not a general HTML sanitizer — it only needs to cover our editor's output.
     */
    private function sanitizeBlockHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        // NOIMPLIED/NODEFDTD stop DOMDocument from injecting <html>/<body>/DOCTYPE;
        // the explicit <body> wrapper gives us a single, known root to walk.
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><body>' . $html . '</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $body = $dom->getElementsByTagName('body')->item(0);
        if (! $body) {
            return '';
        }

        $out = '';
        foreach (iterator_to_array($body->childNodes) as $child) {
            $out .= $this->sanitizeNode($child);
        }

        return trim($out);
    }

    /** Recursively sanitize one DOM node into an allowlisted HTML string. */
    private function sanitizeNode(\DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return e($node->textContent);
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return ''; // comments, PIs, etc.
        }

        $tag = strtolower($node->nodeName);

        // Disallowed element: unwrap it — keep its (sanitized) children so no
        // authored text is lost, drop the tag itself.
        if (! in_array($tag, self::RICHTEXT_TAGS, true)) {
            return $this->sanitizeChildren($node);
        }

        if ($tag === 'br') {
            return '<br>';
        }

        if ($tag === 'img') {
            $src = $this->safeUrl((string) $node->getAttribute('src'));
            if ($src === null) {
                return '';
            }
            $alt = e((string) $node->getAttribute('alt'));

            return '<img src="' . e($src) . '" alt="' . $alt . '">';
        }

        // 'span' only ever carries the editor's text-colour mark (TextStyle +
        // Color: <span style="color:#hex">). Keep ONLY a vetted color/
        // background-color declaration; every other attribute/style is
        // dropped. A span with no valid colour left is unwrapped — it has
        // nothing left worth keeping the wrapper for.
        if ($tag === 'span') {
            $colorStyle = $this->allowedTextColorStyle((string) $node->getAttribute('style'));
            if ($colorStyle === null) {
                return $this->sanitizeChildren($node);
            }

            return '<span style="' . e($colorStyle) . '">' . $this->sanitizeChildren($node) . '</span>';
        }

        // Assemble the allowed attributes for this tag.
        $attrs = '';

        if ($tag === 'a') {
            $href = $this->safeUrl(html_entity_decode((string) $node->getAttribute('href')));
            $attrs .= $href === null ? '' : ' href="' . e($href) . '" rel="noopener"';
        }

        if (in_array($tag, self::RICHTEXT_ALIGNABLE, true)) {
            $align = $this->allowedTextAlign((string) $node->getAttribute('style'));
            if ($align !== null) {
                $attrs .= ' style="text-align:' . $align . '"';
            }
        }

        return "<{$tag}{$attrs}>" . $this->sanitizeChildren($node) . "</{$tag}>";
    }

    /** Concatenate the sanitized output of a node's children. */
    private function sanitizeChildren(\DOMNode $node): string
    {
        $out = '';
        foreach (iterator_to_array($node->childNodes) as $child) {
            $out .= $this->sanitizeNode($child);
        }

        return $out;
    }

    /**
     * Extract a whitelisted text-align value (left|center|right) from a style
     * attribute; everything else in the style string is discarded.
     */
    private function allowedTextAlign(string $style): ?string
    {
        if ($style === '') {
            return null;
        }
        if (preg_match('/text-align\s*:\s*(left|center|right)\b/i', $style, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    /**
     * Extract a vetted `color` (and optional `background-color`) declaration
     * from a <span> style attribute; everything else in the style string
     * (or an unrecognised/invalid colour value) is discarded. Returns null
     * when nothing valid is present.
     */
    private function allowedTextColorStyle(string $style): ?string
    {
        if ($style === '') {
            return null;
        }

        $declarations = [];

        if (preg_match('/(?:^|;)\s*color\s*:\s*([^;]+)/i', $style, $m)) {
            $value = trim($m[1]);
            if ($this->isValidCssColorValue($value)) {
                $declarations[] = 'color:' . $value;
            }
        }

        if (preg_match('/(?:^|;)\s*background-color\s*:\s*([^;]+)/i', $style, $m)) {
            $value = trim($m[1]);
            if ($this->isValidCssColorValue($value)) {
                $declarations[] = 'background-color:' . $value;
            }
        }

        return $declarations === [] ? null : implode(';', $declarations);
    }

    /**
     * Validate a single CSS colour value: hex (#rgb/#rgba/#rrggbb/#rrggbbaa),
     * rgb()/rgba() with numeric channels only, or a plain alphabetic named
     * colour (e.g. "red", "tomato", "currentColor"). Anything else — a
     * `url(...)`, an `expression(...)`, extra tokens like `!important`, etc.
     * — is rejected outright so no stray CSS/markup can ride through a style
     * attribute.
     */
    private function isValidCssColorValue(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (preg_match('/^#[0-9a-f]{3}$|^#[0-9a-f]{4}$|^#[0-9a-f]{6}$|^#[0-9a-f]{8}$/i', $value)) {
            return true;
        }

        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/i', $value)) {
            return true;
        }

        // Named colour keyword — letters only (no digits/punctuation), so
        // e.g. "red", "tomato", "currentColor" pass while anything with
        // parens/semicolons/urls does not.
        if (preg_match('/^[a-z]{3,20}$/i', $value)) {
            return true;
        }

        return false;
    }

    /**
     * Image source: a DAM asset id (preferred — mkt_assets on the public
     * disk, same storage the WordPress publish service reads) or a raw URL.
     */
    private function resolveImageUrl(array $data): ?string
    {
        if (! empty($data['asset_id'])) {
            $asset = MarketingAsset::find((int) $data['asset_id']);

            return ($asset && $asset->file_path)
                ? Storage::disk('public')->url($asset->file_path)
                : null; // asset deleted since the block was authored
        }

        if (! empty($data['url']) && is_string($data['url'])) {
            return $this->safeUrl($data['url']);
        }

        return null;
    }

    /**
     * Allow only http(s), protocol-relative, absolute-path or anchor URLs.
     * Blocks javascript:/data: and other schemes.
     */
    private function safeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (preg_match('#^(https?:)?//#i', $url) || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }

        return null;
    }

    /**
     * Sanitize editor-produced inline HTML for paragraph blocks: allowlisted
     * inline tags only, all attributes stripped except a vetted href on <a>.
     * Not a general-purpose sanitizer — paragraphs only ever carry inline
     * marks from our own editor, so a tight allowlist beats a full library.
     */
    private function sanitizeInlineHtml(string $html): string
    {
        $clean = strip_tags($html, self::ALLOWED_INLINE_TAGS);

        // Normalize EVERY opening <a>: keep only a vetted href, drop all
        // other attributes (onclick=, style=, javascript: hrefs, ...).
        $clean = preg_replace_callback(
            '/<a\b[^>]*>/i',
            function ($m) {
                $href = null;
                if (preg_match('/\bhref\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $m[0], $h)) {
                    $href = $this->safeUrl(html_entity_decode($h[2] !== '' ? $h[2] : ($h[3] ?? '')));
                }

                return $href === null
                    ? '<a>'
                    : '<a href="' . e($href) . '" rel="noopener">';
            },
            $clean
        ) ?? '';

        // Strip attributes from every other allowed opening tag. <a> is
        // excluded — its (already vetted) href must survive this pass.
        $clean = preg_replace('/<(?!a\b|\/)(\w+)\b[^>]*>/i', '<$1>', $clean) ?? '';

        return trim($clean);
    }
}
