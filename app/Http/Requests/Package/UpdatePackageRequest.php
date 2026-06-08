<?php

namespace App\Http\Requests\Package;

use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePackageRequest extends FormRequest
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
        /** @var Package $package */
        $package = $this->route('package');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:packages,name,'.$package->id],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'config' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
