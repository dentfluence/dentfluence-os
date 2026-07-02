<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHuddleCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'body'         => ['required', 'string', 'max:2000'],
            // Optional: link a comment to a specific card
            'huddle_card_id' => ['nullable', 'integer', 'exists:huddle_cards,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required'          => 'Comment body cannot be empty.',
            'huddle_card_id.exists'  => 'The linked card does not exist.',
        ];
    }
}