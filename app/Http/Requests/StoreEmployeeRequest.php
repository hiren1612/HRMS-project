<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ResolvesOtherOptions;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    use ResolvesOtherOptions;

    /**
     * QA M9: fold the "Other…" dropdown choice back into the real field.
     */
    protected function prepareForValidation(): void
    {
        $this->resolveOtherOptions(['department', 'designation']);
    }

    /**
     * Authorization is handled by the route middleware (role:admin,hr),
     * so we just allow the request to proceed to validation here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for creating an employee.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Must point to a real user that doesn't already have a profile.
            'name' => ['required', 'string', 'max:150'],
            'employee_code' => ['required', 'string', 'max:50', 'unique:employees,employee_code'],
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
