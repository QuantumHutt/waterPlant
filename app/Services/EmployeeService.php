<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmployeeService extends BaseService
{
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return Employee::latest()->paginate($perPage);
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): Employee
    {
        $data['code'] = $data['code'] ?? 'EMP-'.str_pad((string) (Employee::max('id') + 1), 4, '0', STR_PAD_LEFT);

        return Employee::create($data);
    }

    /** @param array<string,mixed> $data */
    public function update(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        return $employee;
    }

    public function markAttendance(int $employeeId, string $date, string $status, float $hours = 0): void
    {
        $employee = Employee::findOrFail($employeeId);
        $employee->attendances()->updateOrCreate(['date' => $date], ['status' => $status, 'hours' => $hours]);
    }

    public function addAdvance(int $employeeId, float $amount, ?string $note = null): void
    {
        Employee::findOrFail($employeeId)->advances()->create([
            'amount' => $amount,
            'taken_at' => now()->toDateString(),
            'note' => $note,
        ]);
    }

    /** Generate (or refresh) a month's payroll: net = basic + overtime − advances − deductions. */
    public function generatePayroll(Employee $employee, string $month): Payroll
    {
        return $this->transaction(function () use ($employee, $month): Payroll {
            $overtime = (float) $employee->overtimes()->where('date', 'like', "{$month}%")->sum('amount');
            $advances = (float) $employee->advances()->where('settled', false)->sum('amount');
            $basic = (float) $employee->basic_salary;
            $net = round($basic + $overtime - $advances, 2);

            return Payroll::updateOrCreate(
                ['employee_id' => $employee->id, 'month' => $month],
                ['basic' => $basic, 'overtime' => $overtime, 'advances' => $advances, 'deductions' => 0, 'net' => $net, 'status' => 'unpaid'],
            );
        });
    }
}
