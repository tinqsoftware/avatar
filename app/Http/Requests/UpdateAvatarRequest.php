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
            'voice_mode' => ['required', Rule::in(['synthetic', 'cloned'])],
            'voice_profile' => ['nullable', 'string', 'max:100', Rule::requiredIf($this->input('voice_mode') === 'synthetic'), Rule::in(['anita'])],
            'voice_sample' => ['nullable', 'file', 'mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/x-m4a', Rule::requiredIf($this->input('voice_mode') === 'cloned' && ! $this->route('avatar')?->voice_sample_path), 'max:25600'],
            'status' => ['required', 'in:draft,generating,published'],
            'rive' => ['nullable', 'file', 'extensions:riv', 'max:20480'],
            'background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:max_width=4096,max_height=4096'],
        ];
    }
}
