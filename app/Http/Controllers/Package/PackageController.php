<?php

namespace App\Http\Controllers\Package;

use App\Http\Controllers\Controller;
use App\Http\Requests\Package\UpdatePackageRequest;
use App\Models\Package;
use App\Services\PackageManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    /**
     * @return array<string, string>
     */
    private function categories(): array
    {
        return [
            Package::CATEGORY_COMMUNICATION => 'Comunicación',
            Package::CATEGORY_BUSINESS => 'Negocios',
            Package::CATEGORY_GENERAL => 'General',
        ];
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Package::class);

        $packages = Package::query()
            ->latest('id')
            ->get()
            ->map(fn (Package $package): array => $package->present())
            ->all();

        return Inertia::render('admin/paquetes/index', [
            'packages' => $packages,
            'categories' => $this->categories(),
        ]);
    }

    public function edit(Request $request, Package $package): Response
    {
        $this->authorize('update', $package);

        return Inertia::render('admin/paquetes/edit', [
            'package' => $package->present(),
        ]);
    }

    public function update(UpdatePackageRequest $request, Package $package): RedirectResponse
    {
        $this->authorize('update', $package);

        $package->update($request->validated());

        return to_route('admin.paquetes.index')
            ->with('success', 'Paquete actualizado.');
    }

    public function toggle(Request $request, Package $package, PackageManager $packages): RedirectResponse
    {
        $this->authorize('toggle', $package);

        $package->update(['enabled' => ! $package->enabled]);
        $packages->forget();

        $state = $package->enabled ? 'activado' : 'desactivado';

        return back()->with('success', "Paquete {$state}.");
    }
}
