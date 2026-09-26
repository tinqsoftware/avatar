<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationBundleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $token = config('avatar.sync_token');

        return config('avatar.audio_role') === 'delivery'
            && is_string($token)
            && $token !== ''
            && hash_equals($token, (string) $this->bearerToken());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'bundle' => ['required', 'file', 'mimes:zip', 'max:512000'],
        ];
    }
}
