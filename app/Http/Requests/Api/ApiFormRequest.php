<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * ApiFormRequest
 * --------------
 * Base for all API form requests. When validation fails it returns the SAME
 * envelope every other API response uses:
 *
 *   { "success": false, "message": "...", "errors": { field: [..] } }   (422)
 *
 * so the mobile app can parse failures exactly like successes.
 */
abstract class ApiFormRequest extends FormRequest
{
    /** Route-level middleware (auth:sanctum + api.role) already gate access. */
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'The given data was invalid.',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
