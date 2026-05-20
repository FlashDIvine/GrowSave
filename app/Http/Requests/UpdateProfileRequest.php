<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'name' => 'sometimes|nullable|string|max:255',

            'password' => 'sometimes|nullable|string|min:8',

            'profile_photo' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:2048',

            'whatsapp_number' => 'sometimes|nullable|string|max:20',

            'house_block' => 'sometimes|nullable|string|max:50',

            'house_number' => 'sometimes|nullable|string|max:50'
        ];
    }
}
