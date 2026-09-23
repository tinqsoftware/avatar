<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAvatarRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'regex:/^[a-z0-9-]{2,80}$/', 'unique:avatars,slug'],
            'public_title' => ['required', 'string', 'max:150'],
            'voice_profile' => ['required', 'in:anita'],
            'rive' => ['nullable', 'file', 'extensions:riv', 'max:20480'],
        ];
    }
}
