<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillRequest extends FormRequest
{
    /**
     * Authorize
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',

            'description' => 'nullable|string',

            'amount' => 'required|numeric|min:0',

            'due_date' => 'required|date',

            'status' => 'nullable|in:active,closed'
        ];
    }
}
