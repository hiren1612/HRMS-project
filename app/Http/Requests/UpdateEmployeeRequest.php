<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesOtherOptions;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    use ResolvesOtherOptions;

    /**
     * QA M9: fold the "Other…" dropdown choice back into the real field.
     */
    protected function prepareForValidation(): void
    {
        $this->resolveOtherOptions(['department', 'designation']);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for updating an employee.
     *
     * The unique rules ignore the current employee record so saving an
     * unchanged code/user_id doesn't trip the "already taken" error.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return [
            // A login account is optional: an employee can be an HR record only.
            'user_id' => [
                'nullable', 'integer', 'exists:users,id',
                Rule::unique('employees', 'user_id')->ignore($employee->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'employee_code' => [
                'required', 'string', 'max:50',
                Rule::unique('employees', 'employee_code')->ignore($employee->id),
            ],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s()]+$/', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'designation' => ['required', 'string', 'max:100'],
            'department' => ['required', 'string', 'max:100'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'hire_date' => ['required', 'date'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'status' => ['required', Rule::in(Employee::STATUSES)],
        ];
    }
}
