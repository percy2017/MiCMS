<?php

namespace Modules\ChatBot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartSessionRequest extends FormRequest
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
        $action = (string) $this->input('action', 'login');

        if ($action === 'resume') {
            return [
                'action' => ['required', 'in:resume'],
            ];
        }

        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => [$action === 'register' ? 'required' : 'nullable', 'string', 'min:8', 'max:255'],
            'name' => [$action === 'register' ? 'required' : 'nullable', 'string', 'max:255'],
            'action' => ['required', 'in:login,register'],
            'page_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
