<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\EmployeeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(private readonly EmployeeService $service) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('employees.view'), 403);

        return Inertia::render('Employees/Index', [
            'employees' => $this->service->list((int) $request->integer('per_page', 15)),
            'payrolls' => Payroll::with('employee')->latest()->limit(50)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('employees.create'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'cnic' => ['nullable', 'string', 'max:20'],
            'join_date' => ['nullable', 'date'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
        ]);
        $this->service->create($data);

        return back()->with('success', 'Employee added.');
    }

    public function attendance(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('employees.update'), 403);
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'status' => ['required', 'in:present,absent,leave,half_day'],
            'hours' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->service->markAttendance($data['employee_id'], $data['date'], $data['status'], (float) ($data['hours'] ?? 0));

        return back()->with('success', 'Attendance marked.');
    }

    public function advance(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('employees.update'), 403);
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $this->service->addAdvance($data['employee_id'], (float) $data['amount'], $data['note'] ?? null);

        return back()->with('success', 'Advance recorded.');
    }

    public function payroll(Request $request, Employee $employee): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.process'), 403);
        $data = $request->validate(['month' => ['required', 'string', 'max:7']]);
        $this->service->generatePayroll($employee, $data['month']);

        return back()->with('success', 'Payroll generated.');
    }
}
