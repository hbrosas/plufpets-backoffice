<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommissionPeriodRequest;
use App\Support\EmployeeSession;
use App\Support\Peso;
use App\Support\ShopReports;
use Inertia\Inertia;
use Inertia\Response;

class MyCommissionController extends Controller
{
    public function index(CommissionPeriodRequest $request, ShopReports $reports): Response
    {
        $period = $request->period();
        $employeeId = EmployeeSession::current()['id'] ?? null;

        return Inertia::render('Me/Commissions', [
            'account' => $employeeId !== null,
            'filters' => $period->toArray(),
            'report' => $employeeId === null
                ? [
                    'label' => $period->label,
                    'total' => Peso::format(0),
                    'employees' => [],
                ]
                : $reports->commissions($period, $employeeId),
            'lines' => $employeeId === null ? [] : $reports->commissionLines($period, $employeeId),
        ]);
    }
}
