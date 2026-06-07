<?php

namespace App\Http\Requests\Media;

use App\Support\MediaStorage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MediaUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxSize = MediaStorage::effectiveMaxSize();
        $maxSizeKb = (int) ceil($maxSize / 1024);

        return [
            'file' => [
                'required',
                'file',
                "max:{$maxSizeKb}",
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = (int) MediaStorage::effectiveMaxSize() / 1024 / 1024;

        return [
            'file.max' => __('The file must not be larger than :max MB.', [
                'max' => $maxMb,
            ]),
            'file.uploaded' => __(
                'The file could not be uploaded. Check that the server allows uploads of at least :max MB (current PHP upload_max_filesize is :php_max).',
                [
                    'max' => $maxMb,
                    'php_max' => (string) ini_get('upload_max_filesize'),
                ],
            ),
        ];
    }

    /**
     * Configure the validator to reject blocked file extensions.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $file = $this->file('file');

            if ($file === null) {
                return;
            }

            $blocked = (array) config('media.blocked_extensions', []);

            if ($blocked === []) {
                return;
            }

            $extension = strtolower($file->getClientOriginalExtension());

            if (in_array($extension, $blocked, true)) {
                $validator->errors()->add(
                    'file',
                    __('Files with the :ext extension are not allowed.', ['ext' => $extension]),
                );
            }
        });
    }
}
