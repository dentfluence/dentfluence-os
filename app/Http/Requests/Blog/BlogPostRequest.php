<?php

namespace App\Http\Requests\Blog;

use App\Services\Blog\BlogBlockSchema;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for Blog Hub write requests. Concrete requests override
 * baseRules() for their required/optional shape; this base contributes the
 * common field rules and the block-JSON structural check (BlogBlockSchema).
 *
 * Authorization is handled upstream by the marketing route middleware
 * (`marketing.active` + `module:marketing`) and the `blog.hub` feature gate in
 * the controller, so authorize() is permissive here.
 */
abstract class BlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Field rules common to store/update/autosave (all optional here). */
    protected function commonRules(): array
    {
        return [
            'slug'              => ['nullable', 'string', 'max:255'],
            'excerpt'           => ['nullable', 'string', 'max:1000'],
            'status'            => ['nullable', 'in:draft,scheduled,published,archived'],
            'category_id'       => ['nullable', 'integer', 'exists:blog_categories,id'],
            'featured_asset_id' => ['nullable', 'integer', 'exists:mkt_assets,id'],
            'author_id'         => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_at'      => ['nullable', 'date'],

            // Canonical block document. Structural validity is checked in
            // withValidator() via BlogBlockSchema; here we only assert shape.
            'body_json'          => ['nullable', 'array'],
            'body_json.version'  => ['nullable', 'integer'],
            'body_json.blocks'   => ['nullable', 'array'],

            // 1:1 SEO workspace.
            'seo'                     => ['nullable', 'array'],
            'seo.focus_keyword'       => ['nullable', 'string', 'max:255'],
            'seo.secondary_keywords'  => ['nullable', 'array'],
            'seo.meta_title'          => ['nullable', 'string', 'max:255'],
            'seo.meta_description'    => ['nullable', 'string', 'max:500'],
            'seo.canonical_url'       => ['nullable', 'string', 'max:2048'],
            'seo.og_title'            => ['nullable', 'string', 'max:255'],
            'seo.og_description'      => ['nullable', 'string', 'max:500'],
            'seo.og_image_asset_id'   => ['nullable', 'integer', 'exists:mkt_assets,id'],
            'seo.noindex'             => ['nullable', 'boolean'],

            // Tags (m:n).
            'tag_ids'   => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:blog_tags,id'],
        ];
    }

    /**
     * Run the block-JSON structural validator once the field rules pass.
     * Errors from BlogBlockSchema are surfaced under the body_json key.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $body = $this->input('body_json');
            if (is_array($body)) {
                foreach (BlogBlockSchema::validate($body) as $error) {
                    $v->errors()->add('body_json', $error);
                }
            }
        });
    }
}
