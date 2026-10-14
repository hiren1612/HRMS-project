<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeProfileRequest extends FormRequest
{
    /**
     * Any logged-in user may manage their OWN employee details.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Hybrid model: employees may edit these fields themselves.
     * employee_code and salary are intentionally NOT here — they are HR-only
     * and never accepted from this self-service form.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Designation, department and hire date are HR-owned and are not
            // accepted here: an employee must not be able to re-grade or
            // re-date their own employment (QA H5).
            'phone' => ['nullable', 'string', 'regex:/^[0-9+\-\s()]+$/', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
        ];
    }
}
