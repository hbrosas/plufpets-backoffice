<?php

namespace App\Http\Controllers;

use App\Actions\ImportCatalogItems;
use App\Http\Requests\ImportCatalogItemsRequest;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogItemImportController extends Controller
{
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo "type,name,description,category,brand,variant_name,sku,barcode,price,is_active\n";
            echo "product,Organic kibble,Daily dry food,Food,Pluf Pets,1kg,KIB-001,4800123456789,29.90,1\n";
            echo "service,Full groom,Bath and haircut,Grooming,,Standard,GRM-001,4800987654321,45.00,1\n";
        }, 'catalog-items-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(ImportCatalogItemsRequest $request, ImportCatalogItems $importCatalogItems): RedirectResponse
    {
        $result = $importCatalogItems->handle($request->file('file'));

        $status = $result['imported'] === 1
            ? 'Imported 1 catalog row.'
            : 'Imported '.$result['imported'].' catalog rows.';

        if ($result['errors'] !== []) {
            $status .= ' Some rows were skipped.';
        }

        return redirect()
            ->route('shop.catalog.index')
            ->with('status', $status)
            ->with('import_errors', $result['errors']);
    }
}
