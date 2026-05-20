<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBillRequest extends FormRequest
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
            'title' => 'sometimes|required|string|max:255',

            'description' => 'sometimes|nullable|string',

            'amount' => 'sometimes|required|numeric|min:0',

            'due_date' => 'sometimes|required|date',

            'status' => 'sometimes|required|in:active,closed'
        ];
    }
}
