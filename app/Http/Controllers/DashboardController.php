<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommissionPeriodRequest;
use App\Models\Employee;
use App\Support\DashboardPulse;
use App\Support\EmployeeSession;
use App\Support\ReportPeriod;
use App\Support\ShopReports;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(CommissionPeriodRequest $request, ShopReports $reports, DashboardPulse $pulse): Response
    {
        $canSeeSales = EmployeeSession::allows('sales.view');
        $canSeeAllCommissions = $canSeeSales || EmployeeSession::allows('staff.manage');
        $employee = EmployeeSession::current();

        return Inertia::render('Dashboard', [
            'activeStaffCount' => EmployeeSession::allows('staff.manage')
                ? Employee::query()->where('is_active', true)->count()
                : null,
            'pulse' => $canSeeSales ? $pulse->today() : null,
            'commissions' => $this->commissions($request, $reports, $canSeeAllCommissions, $employee['id'] ?? null),
            'stock' => EmployeeSession::allows('inventory.view') ? $pulse->stock() : null,
        ]);
    }

    /**
     * @return array{scope: string, filters?: array{preset: string, from: string, to: string, label: string}, report: array{label: string, total: string, employees: list<array{name: string, employee_number: string, amount: string}>}}|null
     */
    private function commissions(
        CommissionPeriodRequest $request,
        ShopReports $reports,
        bool $canSeeAllCommissions,
        ?int $employeeId,
    ): ?array {
        if ($canSeeAllCommissions) {
            return [
                'scope' => 'all',
                'report' => $reports->commissions(ReportPeriod::monthToDate()),
            ];
        }

        if ($employeeId === null) {
            return null;
        }

        $period = $request->period();

        return [
            'scope' => 'self',
            'filters' => $period->toArray(),
            'report' => $reports->commissions($period, $employeeId),
        ];
    }
}
