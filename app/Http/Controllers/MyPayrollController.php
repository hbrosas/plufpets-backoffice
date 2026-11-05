<?php

namespace App\Http\Controllers;

use App\Enums\PayrollFilingStatus;
use App\Enums\PayrollFilingType;
use App\Http\Requests\StorePayrollFilingRequest;
use App\Models\EmployeePayroll;
use App\Models\PayrollFiling;
use App\Support\EmployeeSession;
use App\Support\Peso;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MyPayrollController extends Controller
{
    public function index(): Response
    {
        $employeeId = EmployeeSession::current()['id'] ?? null;

        return Inertia::render('Me/Payroll', [
            'account' => $employeeId !== null,
            'types' => PayrollFilingType::options(),
            'filings' => $employeeId === null
                ? []
                : PayrollFiling::query()
                    ->with('employee:id,name,employee_number')
                    ->where('employee_id', $employeeId)
                    ->latest('id')
                    ->limit(50)
                    ->get()
                    ->map(fn (PayrollFiling $filing): array => $filing->toList())
                    ->all(),
            'payrolls' => $employeeId === null
                ? []
                : EmployeePayroll::query()
                    ->where('employee_id', $employeeId)
                    ->latest('period_end')
                    ->latest('id')
                    ->limit(24)
                    ->get()
                    ->map(fn (EmployeePayroll $payroll): array => [
                        'id' => $payroll->id,
                        'period' => $payroll->period_start->format('M j, Y').' – '.$payroll->period_end->format('M j, Y'),
                        'net' => Peso::format($payroll->net),
                    ])
                    ->all(),
        ]);
    }

    public function store(StorePayrollFilingRequest $request): RedirectResponse
    {
        $employeeId = EmployeeSession::current()['id'] ?? null;
        abort_if($employeeId === null, 403);

        $type = PayrollFilingType::from($request->string('type')->toString());

        PayrollFiling::query()->create([
            'employee_id' => $employeeId,
            'type' => $type,
            'status' => PayrollFilingStatus::Pending,
            'starts_on' => $request->validated('starts_on'),
            'ends_on' => $type === PayrollFilingType::Leave ? $request->validated('ends_on') : null,
            'hours' => $type === PayrollFilingType::Overtime ? $request->validated('hours') : null,
            'nights' => $type === PayrollFilingType::Overnight ? $request->validated('nights') : null,
            'reason' => $request->validated('reason'),
        ]);

        return back()->with('status', $type->label().' filed.');
    }

    public function destroy(PayrollFiling $payrollFiling): RedirectResponse
    {
        $employeeId = EmployeeSession::current()['id'] ?? null;

        abort_unless(
            $employeeId !== null
                && $payrollFiling->employee_id === $employeeId
                && $payrollFiling->status === PayrollFilingStatus::Pending,
            404,
        );

        $payrollFiling->delete();

        return back()->with('status', 'Filing withdrawn.');
    }

    public function download(EmployeePayroll $employeePayroll, EmployeePayrollController $payrolls): SymfonyResponse
    {
        $employeeId = EmployeeSession::current()['id'] ?? null;
        abort_unless($employeeId !== null && $employeePayroll->employee_id === $employeeId, 404);

        return $payrolls->downloadFinalized($employeePayroll);
    }
}
