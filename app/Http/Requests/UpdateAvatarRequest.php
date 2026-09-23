<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAvatarRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'regex:/^[a-z0-9-]{2,80}$/', Rule::unique('avatars', 'slug')->ignore($this->route('avatar'))],
            'public_title' => ['required', 'string', 'max:150'],
            'voice_profile' => ['required', 'in:anita'],
            'status' => ['required', 'in:draft,generating,published'],
            'rive' => ['nullable', 'file', 'extensions:riv', 'max:20480'],
        ];
    }
}
