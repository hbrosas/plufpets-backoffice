<?php

namespace App\Http\Controllers;

use App\Enums\PayrollFilingStatus;
use App\Http\Requests\ReviewPayrollFilingRequest;
use App\Models\PayrollFiling;
use App\Support\EmployeeSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PayrollApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->toString() ?: PayrollFilingStatus::Pending->value;

        $request->merge(['tab' => $tab]);

        $filters = $request->validate([
            'tab' => ['required', Rule::enum(PayrollFilingStatus::class)],
        ]);

        $status = PayrollFilingStatus::from($filters['tab']);

        return Inertia::render('Staff/Approvals/Index', [
            'filings' => PayrollFiling::query()
                ->with('employee:id,name,employee_number')
                ->where('status', $status)
                ->orderBy('starts_on')
                ->orderBy('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (PayrollFiling $filing): array => $filing->toList()),
            'filters' => [
                'tab' => $status->value,
            ],
            'tabs' => PayrollFilingStatus::options(),
        ]);
    }

    public function update(ReviewPayrollFilingRequest $request, PayrollFiling $payrollFiling): RedirectResponse
    {
        abort_unless($payrollFiling->status === PayrollFilingStatus::Pending, 404);

        $payrollFiling->update([
            'status' => PayrollFilingStatus::from($request->string('status')->toString()),
            'review_note' => $request->validated('review_note'),
            'reviewed_by_name' => EmployeeSession::current()['name'] ?? '',
            'reviewed_at' => now(),
        ]);

        return back()->with('status', 'Filing '.$payrollFiling->status->label().'.');
    }
}
