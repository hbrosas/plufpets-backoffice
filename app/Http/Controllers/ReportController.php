<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRangeRequest;
use App\Support\EmployeeSession;
use App\Support\PeriodReports;
use App\Support\ReportExport;
use App\Support\ShopReports;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function sales(ReportRangeRequest $request, PeriodReports $reports): Response
    {
        return $this->page($request, 'Reports/Sales', 'sales', $reports->sales($request->period()));
    }

    public function exportSales(ReportRangeRequest $request, ReportExport $export): StreamedResponse
    {
        return $export->sales($request->period());
    }

    public function commissions(ReportRangeRequest $request, ShopReports $reports): Response
    {
        $employeeId = $this->commissionEmployeeId();
        $period = $request->period();

        return Inertia::render('Reports/Commissions', [
            'filters' => $period->toArray(),
            'scope' => $employeeId === null ? 'all' : 'self',
            'report' => $reports->commissions($period, $employeeId),
        ]);
    }

    public function exportCommissions(ReportRangeRequest $request, ReportExport $export): StreamedResponse
    {
        return $export->commissions($request->period(), $this->commissionEmployeeId());
    }

    public function profit(ReportRangeRequest $request, PeriodReports $reports): Response
    {
        return $this->page($request, 'Reports/Profit', 'profit', $reports->profit($request->period()));
    }

    public function exportProfit(ReportRangeRequest $request, ReportExport $export): StreamedResponse
    {
        return $export->profit($request->period());
    }

    public function inventory(ReportRangeRequest $request, PeriodReports $reports): Response
    {
        return $this->page($request, 'Reports/Inventory', 'inventory', $reports->inventory($request->period()));
    }

    public function exportInventory(ReportRangeRequest $request, ReportExport $export): StreamedResponse
    {
        return $export->inventory($request->period());
    }

    public function products(ReportRangeRequest $request, PeriodReports $reports): Response
    {
        return $this->page($request, 'Reports/Products', 'products', $reports->products($request->period()));
    }

    public function exportProducts(ReportRangeRequest $request, ReportExport $export): StreamedResponse
    {
        return $export->products($request->period());
    }

    public function purchasing(ReportRangeRequest $request, PeriodReports $reports): Response
    {
        return $this->page($request, 'Reports/Purchasing', 'purchasing', $reports->purchasing($request->period()));
    }

    public function exportPurchasing(ReportRangeRequest $request, ReportExport $export): StreamedResponse
    {
        return $export->purchasing($request->period());
    }

    public function staff(ReportRangeRequest $request, PeriodReports $reports): Response
    {
        return $this->page($request, 'Reports/Staff', 'staff', $reports->staff($request->period()));
    }

    public function exportStaff(ReportRangeRequest $request, ReportExport $export): StreamedResponse
    {
        return $export->staff($request->period());
    }

    public function reconciliation(ReportRangeRequest $request, PeriodReports $reports): Response
    {
        return $this->page($request, 'Reports/Reconciliation', 'reconciliation', $reports->reconciliation($request->period()));
    }

    public function exportReconciliation(ReportRangeRequest $request, ReportExport $export): StreamedResponse
    {
        return $export->reconciliation($request->period());
    }

    public function payables(PeriodReports $reports): Response
    {
        return Inertia::render('Reports/Payables', [
            'payables' => $reports->payables(),
        ]);
    }

    public function exportPayables(ReportExport $export): StreamedResponse
    {
        return $export->payables();
    }

    private function commissionEmployeeId(): ?int
    {
        if (EmployeeSession::allows('sales.view') || EmployeeSession::allows('staff.manage')) {
            return null;
        }

        return EmployeeSession::current()['id'] ?? 0;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function page(ReportRangeRequest $request, string $component, string $key, array $report): Response
    {
        return Inertia::render($component, [
            'filters' => $request->period()->toArray(),
            $key => $report,
        ]);
    }
}
