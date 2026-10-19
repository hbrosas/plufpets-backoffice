<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BrandController extends Controller
{
    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $brand = new Brand(['name' => $request->validated('name')]);
        $this->syncImage($brand, $request->file('image'));
        $brand->save();

        return back()->with('status', 'Brand created.')->with('created_brand', $brand->name);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $brand->name = $request->validated('name');
        $this->syncImage(
            $brand,
            $request->file('image'),
            $request->boolean('remove_image'),
        );
        $brand->save();

        return back()->with('status', 'Brand updated.')->with('created_brand', $brand->name);
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->image_path) {
            Storage::disk('public')->delete($brand->image_path);
        }

        $brand->delete();

        return back()->with('status', 'Brand deleted.');
    }

    private function syncImage(Brand $brand, ?UploadedFile $image, bool $removeImage = false): void
    {
        if ($image instanceof UploadedFile) {
            if ($brand->image_path) {
                Storage::disk('public')->delete($brand->image_path);
            }

            $brand->image_path = $image->store('brand-logos', 'public');

            return;
        }

        if ($removeImage && $brand->image_path) {
            Storage::disk('public')->delete($brand->image_path);
            $brand->image_path = null;
        }
    }
}
