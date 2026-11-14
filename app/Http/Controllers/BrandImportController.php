<?php

namespace App\Http\Controllers;

use App\Actions\ImportBrands;
use App\Http\Requests\ImportBrandsRequest;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrandImportController extends Controller
{
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo "name\n";
            echo "Pluf Pets\n";
        }, 'brands-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(ImportBrandsRequest $request, ImportBrands $importBrands): RedirectResponse
    {
        $result = $importBrands->handle($request->file('file'));
        $status = $result['imported'] === 1
            ? 'Imported 1 brand.'
            : 'Imported '.$result['imported'].' brands.';

        if ($result['errors'] !== []) {
            $status .= ' Some rows were skipped.';
        }

        return redirect()
            ->route('shop.catalog-meta.index')
            ->with('status', $status)
            ->with('import_errors', $result['errors']);
    }
}
