<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The employee listing (search + pagination) and the simplified create form,
 * where an employee is an HR record in its own right and a login account is
 * optional.
 */
class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function hr(): User
    {
        return User::factory()->create(['role' => User::ROLE_HR]);
    }

    private function employee(array $attributes = []): Employee
    {
        return Employee::create(array_merge([
            'name' => 'Test Person',
            'employee_code' => 'EMP-' . fake()->unique()->numerify('####'),
            'designation' => 'Tester',
            'department' => 'QA',
            'hire_date' => '2026-01-01',
            'status' => Employee::STATUS_ACTIVE,
        ], $attributes));
    }

    // ---------------------------------------------------------------- listing

    public function test_hr_sees_the_employee_list(): void
    {
        $this->employee(['name' => 'Alice Allen']);

        $this->actingAs($this->hr())->get(route('employees.index'))
            ->assertOk()
            ->assertSee('Alice Allen');
    }

    public function test_the_list_shows_ten_per_page(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->employee(['name' => 'Person ' . $i]);
        }

        $response = $this->actingAs($this->hr())->get(route('employees.index'));

        $this->assertCount(10, $response->viewData('employees')->items());
        $this->assertSame(12, $response->viewData('employees')->total());
        $this->assertTrue($response->viewData('employees')->hasPages());
    }

    public function test_search_matches_name_code_designation_and_department(): void
    {
        $this->employee(['name' => 'Findable Fiona', 'employee_code' => 'EMP-9001', 'designation' => 'Welder', 'department' => 'Forge']);
        $this->employee(['name' => 'Someone Else', 'employee_code' => 'EMP-9002', 'designation' => 'Clerk', 'department' => 'Admin']);

        foreach (['Fiona', 'EMP-9001', 'Welder', 'Forge'] as $term) {
            $response = $this->actingAs($this->hr())->get(route('employees.index', ['search' => $term]));

            $this->assertSame(1, $response->viewData('employees')->total(), "search for '{$term}'");
            $response->assertSee('Findable Fiona')->assertDontSee('Someone Else');
        }
    }

    public function test_search_also_matches_the_linked_account_email(): void
    {
        $user = User::factory()->create(['name' => 'Linked Len', 'email' => 'len@example.test']);
        $this->employee(['name' => 'Linked Len', 'user_id' => $user->id]);
        $this->employee(['name' => 'Other Person']);

        $response = $this->actingAs($this->hr())->get(route('employees.index', ['search' => 'len@example.test']));

        $this->assertSame(1, $response->viewData('employees')->total());
    }

    public function test_a_search_with_no_matches_says_so(): void
    {
        $this->employee(['name' => 'Alice Allen']);

        $this->actingAs($this->hr())->get(route('employees.index', ['search' => 'nobodyxyz']))
            ->assertOk()
            ->assertSee('No employees match your search');
    }

    public function test_department_and_status_filters_narrow_the_list(): void
    {
        $this->employee(['name' => 'In QA', 'department' => 'QA']);
        $this->employee(['name' => 'In Sales', 'department' => 'Sales']);
        $this->employee(['name' => 'Left Us', 'department' => 'QA', 'status' => Employee::STATUS_INACTIVE]);

        $byDept = $this->actingAs($this->hr())->get(route('employees.index', ['department' => 'QA']));
        $this->assertSame(2, $byDept->viewData('employees')->total());

        $byStatus = $this->actingAs($this->hr())->get(route('employees.index', ['status' => Employee::STATUS_INACTIVE]));
        $this->assertSame(1, $byStatus->viewData('employees')->total());
    }

    // ------------------------------------------------------------- create form

    public function test_the_create_form_asks_for_a_name_and_not_an_account_or_salary(): void
    {
        $this->actingAs($this->hr())->get(route('employees.create'))
            ->assertOk()
            ->assertSee('Employee name')
            ->assertDontSee('User account')
            ->assertDontSee('Salary');
    }

    public function test_an_employee_can_be_created_from_a_name_alone(): void
    {
        $this->actingAs($this->hr())
            ->post(route('employees.store'), [
                'name' => 'Nina New',
                'employee_code' => 'EMP-4242',
                'designation' => 'Analyst',
                'department' => 'Finance',
                'hire_date' => '2026-02-01',
                'status' => Employee::STATUS_ACTIVE,
            ])
            ->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', [
            'name' => 'Nina New',
            'employee_code' => 'EMP-4242',
            'user_id' => null,
        ]);
    }

    public function test_the_name_is_required(): void
    {
        $this->actingAs($this->hr())
            ->post(route('employees.store'), [
                'employee_code' => 'EMP-4243',
                'designation' => 'Analyst',
                'department' => 'Finance',
                'hire_date' => '2026-02-01',
                'status' => Employee::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('employees', 0);
    }

    public function test_display_name_prefers_the_employee_record_over_the_account(): void
    {
        $user = User::factory()->create(['name' => 'Account Name']);

        $withBoth = $this->employee(['name' => 'Record Name', 'user_id' => $user->id]);
        $this->assertSame('Record Name', $withBoth->displayName());

        $recordOnly = $this->employee(['name' => 'Just A Record']);
        $this->assertNull($recordOnly->user_id);
        $this->assertSame('Just A Record', $recordOnly->displayName());
    }

    // ------------------------------------------------------------- QA fixes

    /**
     * QA C1: an employee with no login account could not be saved from the
     * edit form — validation still demanded a user_id.
     */
    public function test_an_employee_without_a_login_can_be_edited(): void
    {
        $employee = $this->employee(['name' => 'Editable Eve', 'designation' => 'Clerk']);

        $this->actingAs($this->hr())
            ->patch(route('employees.update', $employee), [
                'name' => 'Editable Eve',
                'employee_code' => $employee->employee_code,
                'designation' => 'Senior Clerk',
                'department' => 'QA',
                'hire_date' => '2026-01-01',
                'status' => Employee::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('employees.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'designation' => 'Senior Clerk',
            'status' => Employee::STATUS_INACTIVE,
        ]);
    }

    /** QA M8: a phone number is digits and separators, not letters. */
    public function test_a_phone_number_cannot_contain_letters(): void
    {
        $this->actingAs($this->hr())
            ->post(route('employees.store'), [
                'name' => 'Bad Phone',
                'employee_code' => 'EMP-7777',
                'designation' => 'Clerk',
                'department' => 'QA',
                'phone' => 'abcd',
                'hire_date' => '2026-01-01',
                'status' => Employee::STATUS_ACTIVE,
            ])
            ->assertSessionHasErrors('phone');
    }

    public function test_a_normal_phone_number_is_still_accepted(): void
    {
        $this->actingAs($this->hr())
            ->post(route('employees.store'), [
                'name' => 'Good Phone',
                'employee_code' => 'EMP-7778',
                'designation' => 'Clerk',
                'department' => 'QA',
                'phone' => '+91 98765 43210',
                'hire_date' => '2026-01-01',
                'status' => Employee::STATUS_ACTIVE,
            ])
            ->assertSessionHasNoErrors();
    }

    /** QA H6: public sign-up is closed. */
    public function test_the_public_register_page_is_gone(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Intruder', 'email' => 'x@y.test',
            'password' => 'password', 'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'x@y.test']);
    }

    /** QA H4: staff must not be able to delete their own HR record. */
    public function test_only_an_admin_sees_the_delete_account_option(): void
    {
        $staff = $this->employee(['name' => 'Ordinary Olly']);
        $staff->user_id = User::factory()->create(['role' => User::ROLE_EMPLOYEE])->id;
        $staff->save();

        $this->actingAs($staff->user)->get(route('profile.edit'))
            ->assertOk()->assertDontSee('Delete Account');

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('profile.edit'))->assertOk()->assertSee('Delete Account');
    }

    /** QA H5: job details are HR-owned, not self-service. */
    public function test_an_employee_cannot_change_their_own_designation(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_EMPLOYEE]);
        $employee = $this->employee(['name' => 'Self Sam', 'designation' => 'Junior', 'user_id' => $user->id]);

        $this->actingAs($user)->patch(route('profile.employee.update'), [
            'designation' => 'Chief Executive',
            'department' => 'Board',
            'hire_date' => '2000-01-01',
            'phone' => '123456',
        ]);

        $employee->refresh();
        $this->assertSame('Junior', $employee->designation, 'designation must be unchanged');
        $this->assertSame('QA', $employee->department, 'department must be unchanged');
    }
}
