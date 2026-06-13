<?php

namespace Modules\ChatBot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuickReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create quick replies');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shortcut' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_-]+$/', 'unique:quick_replies,shortcut'],
            'title' => ['required', 'string', 'max:100'],
            'content' => ['nullable', 'string', 'max:5000', 'required_without:media_id'],
            'category' => ['nullable', 'string', 'max:50'],
            'media_id' => ['nullable', 'integer', 'exists:media,id', 'required_without:content'],
            'sort' => ['nullable', 'integer'],
            'enabled' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shortcut.regex' => 'El shortcut solo puede contener letras, números, guion y guion bajo.',
            'content.required_without' => 'Debes escribir un contenido o adjuntar un archivo.',
            'media_id.required_without' => 'Debes escribir un contenido o adjuntar un archivo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('shortcut')) {
            $this->merge([
                'shortcut' => ltrim((string) $this->input('shortcut'), '/'),
            ]);
        }
    }
}
