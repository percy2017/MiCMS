<?php

namespace App\Http\Requests\LogViewer;

use Illuminate\Foundation\Http\FormRequest;
use Opcodes\LogViewer\Facades\LogViewer;

class DestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('delete logs') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }

    public function fileIdentifier(): string
    {
        $file = $this->route('file');

        if (! is_string($file)) {
            abort(404);
        }

        if (! preg_match('/^[A-Za-z0-9._\-]+$/', $file)) {
            abort(404);
        }

        if (LogViewer::getFile($file) === null) {
            abort(404);
        }

        return $file;
    }
}
