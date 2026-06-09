<?php

namespace App\Http\Requests\LogViewer;

use Illuminate\Foundation\Http\FormRequest;
use Opcodes\LogViewer\Facades\LogViewer;

class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view logs') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:200'],
            'level' => ['nullable', 'string', 'in:debug,info,notice,warning,error,critical,alert,emergency'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function fileIdentifier(): ?string
    {
        $file = $this->string('file')->toString();

        if ($file === '') {
            return null;
        }

        if (! preg_match('/^[A-Za-z0-9._\-]+$/', $file)) {
            return null;
        }

        if (LogViewer::getFile($file) === null) {
            return null;
        }

        return $file;
    }
}
