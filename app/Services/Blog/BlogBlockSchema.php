<?php

namespace App\Services\Blog;

/**
 * Canonical block-JSON schema for Blog content (Blog Marketing Hub).
 * ----------------------------------------------------------------------------
 * This is the content contract for the whole Hub: the editor (TipTap, later
 * slice) reads/writes it, BlogBlockRenderer turns it into HTML at save/
 * publish time, and future render targets (websites, newsletters, patient
 * education, social) consume the same document. Content is coupled to
 * neither HTML nor any editor.
 *
 * Shape (stored in blog_posts.body_json):
 *
 *   {
 *     "version": 1,
 *     "blocks": [
 *       { "id": "blk_9f2c", "type": "heading",   "data": { "level": 2, "text": "Why gums bleed" } },
 *       { "id": "blk_a01d", "type": "paragraph", "data": { "text": "Plain text…" } },
 *       { "id": "blk_77aa", "type": "image",     "data": { "asset_id": 12, "alt": "Scaling", "caption": "Before/after" } },
 *       { "id": "blk_3b90", "type": "faq",       "data": { "items": [ { "q": "Does it hurt?", "a": "No…" } ] } },
 *       { "id": "blk_c412", "type": "cta",       "data": { "label": "Book a visit", "url": "https://…", "style": "button" } }
 *     ]
 *   }
 *
 * Per-type `data` contracts (V1):
 *   heading   { level: 1-6, text: string }
 *   paragraph { text: string }  OR  { html: string }   (html = limited inline markup from the editor; sanitized on render)
 *   image     { asset_id?: int (mkt_assets.id), url?: string, alt?: string, caption?: string }
 *   quote     { text: string, cite?: string }
 *   table     { rows: string[][] }                      (first row rendered as header)
 *   cta       { label: string, url: string, style?: 'button'|'link' }
 *   faq       { items: [ { q: string, a: string } ] }
 *   divider   { }
 *
 * Evolution rule: NEW block types are added by extending TYPES + the
 * renderer + an editor node — never by a data-model change. Renderers must
 * skip unknown types gracefully so old code can read newer documents.
 */
class BlogBlockSchema
{
    /** Current schema version written by the editor. */
    public const VERSION = 1;

    /** The only block types V1 understands. Order is not significant. */
    public const TYPES = [
        'heading',
        'paragraph',
        'image',
        'quote',
        'table',
        'cta',
        'faq',
        'divider',
    ];

    /**
     * Structural validation for a body_json document. Returns a flat list of
     * human-readable error strings (empty array = valid). Later slices call
     * this on save/autosave before persisting.
     *
     * Deliberately lenient about unknown block types (warning-free skip, per
     * the evolution rule) but strict about the envelope and the known types'
     * required fields.
     *
     * @param array $bodyJson decoded body_json
     * @return array<int, string> validation errors
     */
    public static function validate(array $bodyJson): array
    {
        $errors = [];

        if (! isset($bodyJson['version']) || ! is_int($bodyJson['version'])) {
            $errors[] = 'Document is missing an integer "version".';
        }

        if (! isset($bodyJson['blocks']) || ! is_array($bodyJson['blocks'])) {
            $errors[] = 'Document is missing a "blocks" array.';
            return $errors; // nothing else to check
        }

        foreach ($bodyJson['blocks'] as $i => $block) {
            $label = 'Block #' . ($i + 1);

            if (! is_array($block)) {
                $errors[] = "{$label}: not an object.";
                continue;
            }

            $type = $block['type'] ?? null;
            $data = $block['data'] ?? null;

            if (! is_string($type) || $type === '') {
                $errors[] = "{$label}: missing \"type\".";
                continue;
            }
            if (empty($block['id']) || ! is_string($block['id'])) {
                $errors[] = "{$label} ({$type}): missing string \"id\".";
            }
            if (! is_array($data)) {
                // divider legitimately has empty data, but it must still be an object/array
                $errors[] = "{$label} ({$type}): missing \"data\" object.";
                continue;
            }

            // Unknown types are allowed (forward compatibility) — skip field checks.
            if (! in_array($type, self::TYPES, true)) {
                continue;
            }

            switch ($type) {
                case 'heading':
                    $level = $data['level'] ?? null;
                    if (! is_int($level) || $level < 1 || $level > 6) {
                        $errors[] = "{$label} (heading): \"level\" must be an integer 1-6.";
                    }
                    if (! isset($data['text']) || ! is_string($data['text'])) {
                        $errors[] = "{$label} (heading): missing string \"text\".";
                    }
                    break;

                case 'paragraph':
                    if (! isset($data['text']) && ! isset($data['html'])) {
                        $errors[] = "{$label} (paragraph): needs \"text\" or \"html\".";
                    }
                    break;

                case 'image':
                    if (empty($data['asset_id']) && empty($data['url'])) {
                        $errors[] = "{$label} (image): needs \"asset_id\" or \"url\".";
                    }
                    break;

                case 'quote':
                    if (! isset($data['text']) || ! is_string($data['text'])) {
                        $errors[] = "{$label} (quote): missing string \"text\".";
                    }
                    break;

                case 'table':
                    if (! isset($data['rows']) || ! is_array($data['rows'])) {
                        $errors[] = "{$label} (table): missing \"rows\" array.";
                    }
                    break;

                case 'cta':
                    if (empty($data['label']) || ! is_string($data['label'])) {
                        $errors[] = "{$label} (cta): missing string \"label\".";
                    }
                    if (empty($data['url']) || ! is_string($data['url'])) {
                        $errors[] = "{$label} (cta): missing string \"url\".";
                    }
                    break;

                case 'faq':
                    if (! isset($data['items']) || ! is_array($data['items'])) {
                        $errors[] = "{$label} (faq): missing \"items\" array.";
                        break;
                    }
                    foreach ($data['items'] as $j => $item) {
                        if (! is_array($item) || ! isset($item['q'], $item['a'])) {
                            $errors[] = "{$label} (faq): item #" . ($j + 1) . ' needs "q" and "a".';
                        }
                    }
                    break;

                case 'divider':
                    // no required fields
                    break;
            }
        }

        return $errors;
    }
}
