<?php

namespace App\Http\Requests\Menu;

use App\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuRequest extends FormRequest
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
        /** @var Menu $menu */
        $menu = $this->route('menu');
        $locations = array_keys((array) config('menus.locations', []));

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'location' => [
                'sometimes',
                'required',
                'string',
                Rule::in($locations),
                Rule::unique('menus', 'location')->ignore($menu->id),
            ],
        ];
    }
}
