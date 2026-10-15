<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnnouncementRequest extends FormRequest
{
    /**
     * The route is behind role:admin,hr, so reaching here means authorised.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An unchecked checkbox is simply absent from the request, which would
     * otherwise read as "no change" instead of "unpublish".
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['is_published' => $this->boolean('is_published')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['boolean'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expires_at.after_or_equal' => 'An end date in the past would hide the announcement immediately. Leave it blank to keep it showing.',
        ];
    }
}
