<?php

namespace App\Http\Controllers;

use App\Actions\ImportCategories;
use App\Http\Requests\ImportCategoriesRequest;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryImportController extends Controller
{
    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo "name,pos_category,show_on_shop\n";
            echo "Food,Retail,1\n";
            echo "Grooming,Services,0\n";
        }, 'categories-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(ImportCategoriesRequest $request, ImportCategories $importCategories): RedirectResponse
    {
        $result = $importCategories->handle($request->file('file'));
        $status = $result['imported'] === 1
            ? 'Imported 1 category.'
            : 'Imported '.$result['imported'].' categories.';

        if ($result['errors'] !== []) {
            $status .= ' Some rows were skipped.';
        }

        return redirect()
            ->route('shop.catalog-meta.index')
            ->with('status', $status)
            ->with('import_errors', $result['errors']);
    }
}
