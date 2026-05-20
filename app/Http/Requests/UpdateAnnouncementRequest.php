<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementRequest extends FormRequest
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

            'content' => 'sometimes|required|string',

            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ];
    }
}
