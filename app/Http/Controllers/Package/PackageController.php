<?php

namespace App\Http\Controllers\Package;

use App\Http\Controllers\Controller;
use App\Services\PackageManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Laravel\Module as ModuleInstance;

class PackageController extends Controller
{
    public function index(): Response
    {
        $modules = collect(Module::all())
            ->map(fn (ModuleInstance $module): array => [
                'slug' => $module->getLowerName(),
                'name' => $module->getName(),
                'description' => $module->getDescription(),
                'version' => $module->get('version', '1.0.0'),
                'enabled' => $module->isEnabled(),
                'installed' => true,
                'icon' => $module->json()->get('menu.icon', 'Package'),
            ])
            ->values()
            ->all();

        return Inertia::render('admin/paquetes/index', [
            'packages' => $modules,
            'categories' => [],
        ]);
    }

    public function edit(Request $request, string $slug): Response
    {
        return Inertia::render('admin/paquetes/edit', [
            'package' => [
                'slug' => $slug,
                'name' => $slug,
                'enabled' => Module::isEnabled($slug),
            ],
        ]);
    }

    public function toggle(Request $request, string $slug, PackageManager $packages): RedirectResponse
    {
        $module = Module::find($slug);

        if (! $module) {
            return back()->with('error', 'Módulo no encontrado.');
        }

        if ($module->isEnabled()) {
            $module->disable();
            $state = 'desactivado';
        } else {
            $module->enable();
            $state = 'activado';
        }

        $packages->forget();

        return back()->with('success', "Módulo {$state}.");
    }
}
