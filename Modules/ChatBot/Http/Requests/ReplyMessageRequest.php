<?php

namespace Modules\ChatBot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReplyMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('reply chatbot');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:5000'],
            'attachment_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'file' => ['nullable', 'file', 'max:16384'],
        ];
    }
}
