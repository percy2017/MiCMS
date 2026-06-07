<?php

namespace App\Http\Requests\Menu;

use App\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuItemRequest extends FormRequest
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
        $itemId = $this->route('item');

        return [
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in([Menu::TYPE_CUSTOM, Menu::TYPE_PAGE])],
            'page_id' => ['nullable', 'integer', 'exists:pages,id'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('menu_items', 'id')->where('menu_id', $menu->id),
            ],
            'order' => ['sometimes', 'integer', 'min:0'],
            'target' => ['sometimes', Rule::in([Menu::TARGET_SELF, Menu::TARGET_BLANK])],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('type') === Menu::TYPE_PAGE && ! $this->filled('page_id')) {
                $validator->errors()->add('page_id', __('Selecciona una página para este elemento.'));
            }

            if ($this->input('type') === Menu::TYPE_CUSTOM && ! $this->filled('url')) {
                $validator->errors()->add('url', __('Ingresa una URL para este elemento.'));
            }
        });
    }
}
