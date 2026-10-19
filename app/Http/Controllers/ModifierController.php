<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreModifierRequest;
use App\Http\Requests\UpdateModifierRequest;
use App\Models\Modifier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ModifierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Shop/Modifiers/Index', [
            'modifiers' => Modifier::query()
                ->withCount('catalogItems')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Modifier $modifier): array => [
                    'id' => $modifier->id,
                    'name' => $modifier->name,
                    'options' => $modifier->options ?? [],
                    'is_active' => $modifier->is_active,
                    'item_count' => $modifier->catalog_items_count,
                ]),
        ]);
    }

    public function store(StoreModifierRequest $request): RedirectResponse
    {
        Modifier::query()->create($request->safe()->only(['name', 'options', 'is_active']));

        return redirect()
            ->route('shop.modifiers.index')
            ->with('status', 'Modifier created.');
    }

    public function update(UpdateModifierRequest $request, Modifier $modifier): RedirectResponse
    {
        $modifier->update($request->safe()->only(['name', 'options', 'is_active']));

        return redirect()
            ->route('shop.modifiers.index')
            ->with('status', 'Modifier updated.');
    }

    public function destroy(Modifier $modifier): RedirectResponse
    {
        $modifier->delete();

        return redirect()
            ->route('shop.modifiers.index')
            ->with('status', 'Modifier deleted.');
    }
}
