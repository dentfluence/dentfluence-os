<?php

namespace App\Http\Requests\Blog;

/**
 * Autosave the working content. Intentionally lenient: an in-progress draft
 * may be incomplete, so nothing is required. The service writes an 'autosave'
 * version and prunes to the newest 20.
 */
class AutosaveBlogPostRequest extends BlogPostRequest
{
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'title' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
