<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
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

            'content' => 'required|string',

            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ];
    }
}
