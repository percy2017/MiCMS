<?php

namespace App\Http\Requests\ChatWidget;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_id' => ['nullable', 'string', 'max:64'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
