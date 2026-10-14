<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An employee is now an HR record in its own right: the name lives on the
     * employee, and a login account is optional rather than required.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('name')->nullable()->after('user_id');
        });

        // Carry each existing employee's name across from their account.
        // Written with the query builder rather than UPDATE ... JOIN so the
        // migration also runs on sqlite, which the test suite uses.
        foreach (DB::table('employees')->whereNull('name')->get() as $employee) {
            $user = DB::table('users')->where('id', $employee->user_id)->first();

            if ($user) {
                DB::table('employees')->where('id', $employee->id)->update(['name' => $user->name]);
            }
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows without an account cannot satisfy a NOT NULL user_id, so they
        // are removed before the column is tightened back up.
        DB::table('employees')->whereNull('user_id')->delete();

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->dropColumn('name');
        });
    }
};
