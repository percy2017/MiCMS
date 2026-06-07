<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\ReorderMenuItemsRequest;
use App\Http\Requests\Menu\StoreMenuItemRequest;
use App\Http\Requests\Menu\UpdateMenuItemRequest;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuItemController extends Controller
{
    public function store(StoreMenuItemRequest $request, Menu $menu): RedirectResponse
    {
        $this->authorize('create', MenuItem::class);

        $data = $request->validated();
        $data['menu_id'] = $menu->id;
        $data['order'] = $data['order'] ?? (int) $menu->items()->max('order') + 1;

        if (($data['type'] ?? null) === Menu::TYPE_PAGE) {
            $data['url'] = null;
        } else {
            $data['page_id'] = null;
        }

        $item = MenuItem::create($data);

        return back()->with('success', 'Elemento añadido al menú.');
    }

    public function update(UpdateMenuItemRequest $request, Menu $menu, MenuItem $item): RedirectResponse
    {
        $this->authorize('update', $item);

        abort_unless($item->menu_id === $menu->id, 404);

        $data = $request->validated();

        if (($data['type'] ?? null) === Menu::TYPE_PAGE) {
            $data['url'] = null;
        } elseif (($data['type'] ?? null) === Menu::TYPE_CUSTOM) {
            $data['page_id'] = null;
        }

        $item->update($data);

        return back()->with('success', 'Elemento actualizado.');
    }

    public function destroy(Request $request, Menu $menu, MenuItem $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        abort_unless($item->menu_id === $menu->id, 404);

        DB::transaction(function () use ($item): void {
            $item->children()->update(['parent_id' => $item->parent_id]);
            $item->delete();
        });

        return back()->with('success', 'Elemento eliminado.');
    }

    public function reorder(ReorderMenuItemsRequest $request, Menu $menu): RedirectResponse|JsonResponse
    {
        $items = $request->validated()['items'];

        DB::transaction(function () use ($items, $menu): void {
            foreach ($items as $row) {
                MenuItem::query()
                    ->where('menu_id', $menu->id)
                    ->where('id', $row['id'])
                    ->update([
                        'parent_id' => $row['parent_id'] ?? null,
                        'order' => (int) $row['order'],
                    ]);
            }
        });

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Orden del menú actualizado.');
    }
}
