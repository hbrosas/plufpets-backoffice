<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(): Response
    {
        $branches = Branch::query()
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->through(fn (Branch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->name,
                'registered_business_name' => $branch->registered_business_name,
                'tin' => $branch->tin,
                'code' => $branch->code,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'is_active' => $branch->is_active,
            ]);

        return Inertia::render('Shop/Branches/Index', [
            'branches' => $branches,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Shop/Branches/Create');
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        Branch::query()->create($request->safe()->only([
            'name',
            'registered_business_name',
            'tin',
            'code',
            'address',
            'phone',
            'is_active',
        ]));

        return redirect()
            ->route('shop.branches.index')
            ->with('status', 'Branch created.');
    }

    public function edit(Branch $branch): Response
    {
        return Inertia::render('Shop/Branches/Edit', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'registered_business_name' => $branch->registered_business_name ?? '',
                'tin' => $branch->tin ?? '',
                'code' => $branch->code ?? '',
                'address' => $branch->address ?? '',
                'phone' => $branch->phone ?? '',
                'is_active' => $branch->is_active,
            ],
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $branch->update($request->safe()->only([
            'name',
            'registered_business_name',
            'tin',
            'code',
            'address',
            'phone',
            'is_active',
        ]));

        return redirect()
            ->route('shop.branches.index')
            ->with('status', 'Branch updated.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $branch->delete();

        return redirect()
            ->route('shop.branches.index')
            ->with('status', 'Branch deleted.');
    }
}
