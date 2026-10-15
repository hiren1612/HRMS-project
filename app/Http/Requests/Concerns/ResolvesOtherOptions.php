<?php

namespace App\Http\Requests\Concerns;

/**
 * QA M9: the department and designation dropdowns offer an "Other…" option
 * backed by a free-text box. The browser submits the sentinel "__other" plus
 * a separate "<field>_other" value; this folds the two back into one field
 * before validation, so the rest of the application never sees the sentinel.
 */
trait ResolvesOtherOptions
{
    /**
     * @param  list<string>  $fields
     */
    protected function resolveOtherOptions(array $fields): void
    {
        foreach ($fields as $field) {
            if ($this->input($field) !== '__other') {
                continue;
            }

            $typed = trim((string) $this->input($field . '_other', ''));

            // An empty box leaves the field empty so "required" reports it
            // against the dropdown the user can actually see.
            $this->merge([$field => $typed === '' ? null : $typed]);
        }
    }
}
