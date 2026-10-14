<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    /**
     * Display a paginated list of employees.
     * with('user') eager-loads the related user to avoid N+1 queries in the view.
     */
    public function index(Request $request): View
    {
        $employees = Employee::with('user')
            // Free-text search across the employee record and the linked account.
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%' . $request->string('search') . '%';

                $query->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('employee_code', 'like', $term)
                    ->orWhere('designation', 'like', $term)
                    ->orWhere('department', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)));
            })
            ->when($request->filled('department'), fn ($q) => $q->where('department', $request->string('department')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'departments' => Employee::query()->whereNotNull('department')
                ->distinct()->orderBy('department')->pluck('department'),
        ]);
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create(): View
    {
        return view('employees.create', ['managers' => $this->managerOptions()]);
    }

    /**
     * Persist a new employee. $request->validated() returns only the
     * rules-approved data from StoreEmployeeRequest.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        Employee::create($request->validated());

        return redirect()->route('employees.index')
            ->with('success', 'Employee created successfully.');
    }

    /**
     * Display a single employee (route-model bound).
     */
    public function show(Employee $employee): View
    {
        $employee->load('user');

        return view('employees.show', compact('employee'));
    }

    /**
     * Show the edit form.
     */
    public function edit(Employee $employee): View
    {
        return view('employees.edit', ['employee' => $employee, 'managers' => $this->managerOptions()]);
    }

    /**
     * Apply validated changes to an existing employee.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        return redirect()->route('employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    /**
     * Delete an employee profile (the linked user account is left intact).
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', 'Employee deleted successfully.');
    }

    /**
     * Accounts that can be picked as a line manager, as id => name (role).
     *
     * @return array<int, string>
     */
    private function managerOptions(): array
    {
        return \App\Models\User::whereIn('role', [
                \App\Models\User::ROLE_MANAGER,
                \App\Models\User::ROLE_HR,
                \App\Models\User::ROLE_ADMIN,
            ])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($u) => [$u->id => $u->name . ' (' . $u->role . ')'])
            ->all();
    }
}
