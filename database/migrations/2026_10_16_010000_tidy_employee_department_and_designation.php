<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * QA M9: department and designation were free text, so the same thing was
     * typed several ways. The form now uses dropdowns; this brings the rows
     * that were typed by hand onto the same wording so grouping and filtering
     * work.
     *
     * Only exact, unambiguous matches are rewritten. Anything unrecognised is
     * left alone and still appears in the dropdown.
     */
    private const DEPARTMENTS = [
        'IT' => 'Information Technology',
        'I.T.' => 'Information Technology',
        'HR' => 'Human Resources',
        'Human Resource' => 'Human Resources',
        'Dev' => 'Engineering',
        'Development' => 'Engineering',
        'Accounts' => 'Finance',
        'Support' => 'Customer Support',
        'Admin' => 'Administration',
    ];

    private const DESIGNATIONS = [
        'human resource' => 'HR Executive',
        'Human Resource' => 'HR Executive',
        'hr' => 'HR Executive',
        'developer' => 'Software Developer',
        'Developer' => 'Software Developer',
        'Sr. Software Developer' => 'Senior Software Developer',
        'Jr. Developer' => 'Junior Developer',
        'UIUX Designer' => 'UI/UX Designer',
        'UI UX Designer' => 'UI/UX Designer',
    ];

    public function up(): void
    {
        // Strip stray whitespace first, so " IT " matches "IT" below.
        foreach (['department', 'designation'] as $column) {
            foreach (DB::table('employees')->whereNotNull($column)->get(['id', $column]) as $row) {
                $trimmed = trim((string) $row->{$column});

                if ($trimmed !== $row->{$column}) {
                    DB::table('employees')->where('id', $row->id)->update([$column => $trimmed]);
                }
            }
        }

        foreach (self::DEPARTMENTS as $from => $to) {
            DB::table('employees')->where('department', $from)->update(['department' => $to]);
        }

        foreach (self::DESIGNATIONS as $from => $to) {
            DB::table('employees')->where('designation', $from)->update(['designation' => $to]);
        }
    }

    public function down(): void
    {
        // Deliberately irreversible: the old spellings were the defect, and
        // restoring them would undo a clean-up HR can see in the UI.
    }
};
