<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHuddleSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only admin can update settings
        return auth()->check() && in_array(
            auth()->user()->role,
            ['admin'],
            strict: true
        );
    }

    public function rules(): array
    {
        return [
            // Settings arrive as key-value pairs
            // e.g. { "settings": { "carry_forward_enabled": true, "proof_required_for": ["sterilization"] } }
            'settings'       => ['required', 'array'],
            'settings.*'     => ['present'],   // each value validated below per key if needed
        ];
    }

    public function messages(): array
    {
        return [
            'settings.required' => 'No settings payload provided.',
            'settings.array'    => 'Settings must be a key-value object.',
        ];
    }
}