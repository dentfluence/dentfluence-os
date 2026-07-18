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

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

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
