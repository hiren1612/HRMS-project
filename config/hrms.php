<?php

/*
|--------------------------------------------------------------------------
| HRMS lists
|--------------------------------------------------------------------------
| QA M9: department and designation used to be free text, so the same role
| was typed several ways ("HR Manager" and "human resource", "IT" and
| "Information Technology"). These lists drive the dropdowns on the employee
| form and the filters on the employee list.
|
| Add a new department or designation here and it appears everywhere. The
| form still accepts a value outside the list via "Other", so HR is never
| blocked by a role nobody has added yet.
*/

return [

    'departments' => [
        'Administration',
        'Customer Support',
        'Design',
        'Engineering',
        'Finance',
        'Human Resources',
        'Information Technology',
        'Marketing',
        'Operations',
        'Sales',
    ],

    'designations' => [
        'Intern',
        'Trainee',
        'Junior Developer',
        'Software Developer',
        'Senior Software Developer',
        'Lead Engineer',
        'Engineering Manager',
        'QA Engineer',
        'UI/UX Designer',
        'Web Designer',
        'Graphic Designer',
        'HR Executive',
        'HR Manager',
        'Accountant',
        'Finance Manager',
        'Sales Executive',
        'Marketing Executive',
        'Support Executive',
        'Team Lead',
        'Project Manager',
        'Manager',
        'Director',
    ],

    /*
    | Date display
    |--------------------------------------------------------------------------
    | QA U6: one format everywhere. Use the x-date component or the Carbon
    | macros (->asDate(), ->asDateTime()) rather than calling format() ad hoc.
    */
    'date_format' => 'd M Y',
    'time_format' => 'h:i A',
    'datetime_format' => 'd M Y, h:i A',

];
