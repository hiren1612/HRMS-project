<?php

namespace App\Http\Requests;

use App\Models\Leave;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequest extends FormRequest
{
    /**
     * Delegate to the LeavePolicy: only users who can "create" a leave
     * (i.e. have an employee profile) may submit this form.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Leave::class);
    }

    /**
     * For a single-day request the "Apply for multiple days" box is unchecked,
     * so we only get one date — copy it into end_date so the rules below work
     * unchanged for both single- and multi-day requests.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->boolean('multiple_days')) {
            $this->merge(['end_date' => $this->input('start_date')]);
        }
    }

    /**
     * Note: employee_id and status are intentionally NOT validated here —
     * they are set server-side in the controller, never trusted from input.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'leave_type' => ['required', Rule::in(array_keys(Leave::TYPES))],
            'session' => ['required', Rule::in(array_keys(Leave::SESSIONS))],
            // QA M5: sick leave is often recorded after the day. Back-dating is
            // allowed within a month; it still needs approval either way.
            'start_date' => ['required', 'date', 'after_or_equal:' . now()->subMonth()->toDateString()],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.after_or_equal' => 'Leave can be back-dated by up to one month.',
            'end_date.after_or_equal' => 'The end date must be the same as or after the start date.',
        ];
    }

    /**
     * QA H9: the same dates could be booked twice. If both were approved the
     * day came off the balance twice, so an overlap is refused up front.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $employee = $this->user()->employee;

            if (! $employee || ! $this->filled('start_date') || ! $this->filled('end_date')) {
                return;
            }

            $clash = $employee->leaves()
                ->whereIn('status', [Leave::STATUS_PENDING, Leave::STATUS_APPROVED])
                ->whereDate('start_date', '<=', $this->date('end_date'))
                ->whereDate('end_date', '>=', $this->date('start_date'))
                ->first();

            if ($clash) {
                $validator->errors()->add('start_date', sprintf(
                    'You already have %s leave from %s to %s. Cancel or amend that request first.',
                    $clash->status,
                    $clash->start_date->format('d M Y'),
                    $clash->end_date->format('d M Y'),
                ));
            }
        });
    }
}
