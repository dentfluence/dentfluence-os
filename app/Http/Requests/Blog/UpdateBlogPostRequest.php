<?php

namespace App\Http\Requests\Blog;

/**
 * Update / manual-save a blog post. Every field is optional (partial update);
 * title, when present, must be non-empty. Slug changes are accepted here but
 * the service ignores them once the post has been published (slug lock).
 */
class UpdateBlogPostRequest extends BlogPostRequest
{
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
        ]);
    }
}
