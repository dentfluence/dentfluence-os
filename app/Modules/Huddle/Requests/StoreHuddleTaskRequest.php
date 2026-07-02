<?php

declare(strict_types=1);

namespace App\Modules\Huddle\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHuddleTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // task_id is required — HuddleTaskController::store() only creates
            // a log entry pointing to an existing task, never creates a new task.
            // If task_id is null the log insert will fail with a NOT NULL violation.
            'task_id'        => ['required', 'integer', 'exists:tasks,id'],

            // These fields are informational / for display — not used by store()
            // but kept for future use and frontend convenience
            'title'          => ['nullable', 'string', 'max:255'],
            'task_type'      => ['nullable', 'string', 'in:call,sterilization,whatsapp,general'],
            'assigned_to'    => ['nullable', 'integer', 'exists:users,id'],
            'due_date'       => ['nullable', 'date'],
            'requires_proof' => ['boolean'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'task_id.required' => 'A task ID is required to create a huddle task log.',
            'task_id.exists'   => 'The linked task does not exist.',
            'task_type.in'     => 'Task type must be one of: call, sterilization, whatsapp, general.',
            'assigned_to.exists' => 'The selected user does not exist.',
        ];
    }
}
