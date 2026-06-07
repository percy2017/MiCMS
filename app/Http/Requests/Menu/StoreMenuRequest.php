<?php

namespace App\Http\Requests\Menu;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuRequest extends FormRequest
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
        $locations = array_keys((array) config('menus.locations', []));

        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', Rule::in($locations), 'unique:menus,location'],
        ];
    }
}
