<?php

namespace Modules\ChatBot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWidgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('update chatbot widget');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'greeting' => ['nullable', 'string', 'max:1000'],
            'position' => ['required', 'in:left,right'],
            'avatar_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'require_auth' => ['required', 'boolean'],
            'show_typing' => ['required', 'boolean'],
            'offline_message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
