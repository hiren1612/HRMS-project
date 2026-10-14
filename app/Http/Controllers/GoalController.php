<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Goal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manage employee performance goals. The route group already restricts this
 * to admin/hr, so no extra authorisation is needed here.
 */
class GoalController extends Controller
{
    /**
     * All goals, active ones first, then soonest due.
     */
    public function index(): View
    {
        $goals = Goal::with('employee.user')
            // CASE rather than MySQL's FIELD() so the query also runs on sqlite.
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'completed' THEN 1 ELSE 2 END")
            ->orderByRaw('due_date IS NULL, due_date')
            ->paginate(15);

        return view('goals.index', compact('goals'));
    }

    public function create(): View
    {
        return view('goals.create', ['employees' => $this->employeeOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $data['created_by'] = $request->user()->id;

        Goal::create($data);

        return redirect()->route('goals.index')->with('success', 'Goal assigned successfully.');
    }

    public function edit(Goal $goal): View
    {
        return view('goals.edit', ['goal' => $goal, 'employees' => $this->employeeOptions()]);
    }

    public function update(Request $request, Goal $goal): RedirectResponse
    {
        $goal->update($this->validateData($request));

        return redirect()->route('goals.index')->with('success', 'Goal updated successfully.');
    }

    public function destroy(Goal $goal): RedirectResponse
    {
        $goal->delete();

        return redirect()->route('goals.index')->with('success', 'Goal deleted successfully.');
    }

    /**
     * Shared validation rules for create/update.
     *
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'due_date' => ['nullable', 'date'],
            'status' => ['required', 'in:' . implode(',', Goal::STATUSES)],
        ]);
    }

    /**
     * Employees for the picker, as id => "Name (EMP-code)".
     *
     * @return array<int, string>
     */
    private function employeeOptions(): array
    {
        return Employee::with('user')
            ->get()
            ->sortBy(fn (Employee $e) => $e->displayName())
            ->mapWithKeys(fn (Employee $e) => [
                $e->id => $e->displayName() . ' (' . $e->employee_code . ')',
            ])
            ->all();
    }
}
