<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrinterRequest;
use App\Http\Requests\UpdatePrinterRequest;
use App\Models\Printer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PrinterController extends Controller
{
    public function store(StorePrinterRequest $request): RedirectResponse
    {
        Printer::query()->create($this->printerAttributes($request->validated()));

        return redirect()
            ->route('pos.settings.index')
            ->with('status', 'Printer created.');
    }

    public function update(UpdatePrinterRequest $request, Printer $printer): RedirectResponse
    {
        $printer->update($this->printerAttributes($request->validated()));

        return redirect()
            ->route('pos.settings.index')
            ->with('status', 'Printer updated.');
    }

    public function destroy(Printer $printer): RedirectResponse
    {
        $printer->delete();

        return redirect()
            ->route('pos.settings.index')
            ->with('status', 'Printer deleted.');
    }

    public function test(Printer $printer): View
    {
        return view('printers.test', [
            'printer' => $printer,
            'widthMm' => $printer->paper_width_mm,
            'heightMm' => $printer->paper_height_mm ?? 200,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function printerAttributes(array $validated): array
    {
        $validated['connection_type'] = $validated['connection'];
        unset($validated['connection']);
        $validated['baud_rate'] = $validated['baud_rate'] ?? 9600;

        return $validated;
    }
}
