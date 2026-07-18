<?php

namespace App\Http\Requests\Blog;

/**
 * Create a new blog post (draft). Title is the only hard requirement; the
 * slug is auto-generated when omitted.
 */
class StoreBlogPostRequest extends BlogPostRequest
{
    public function rules(): array
    {
        return array_merge($this->commonRules(), [
            'title' => ['required', 'string', 'max:255'],
        ]);
    }
}
