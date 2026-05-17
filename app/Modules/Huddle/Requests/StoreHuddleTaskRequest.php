<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHuddleTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user can create a huddle task
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:255'],
            'task_type'    => ['required', 'string', 'in:call,sterilization,whatsapp,general'],
            'assigned_to'  => ['nullable', 'integer', 'exists:users,id'],
            'due_date'     => ['nullable', 'date'],
            'requires_proof' => ['boolean'],
            'notes'        => ['nullable', 'string', 'max:1000'],
            // Optional link to an existing task — if provided, we log it
            // instead of creating a duplicate in the tasks table
            'task_id'      => ['nullable', 'integer', 'exists:tasks,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'task_type.in'       => 'Task type must be one of: call, sterilization, whatsapp, general.',
            'assigned_to.exists' => 'The selected user does not exist.',
            'task_id.exists'     => 'The linked task does not exist.',
        ];
    }
}