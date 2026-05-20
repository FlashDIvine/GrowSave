<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
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
            'type' => 'required|in:in,out',
            
            'category' => 'required|string|max:255',
            
            'amount' => 'required|numeric|min:1',
            
            'transaction_date' => 'required|date',
            
            'description' => 'nullable|string'
        ];
    }
}
