<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productivity module — who may view monitoring data.
 *
 * The brief asks for "authorized admins with the appropriate permission",
 * which the existing four roles cannot express: being an admin of the HRMS
 * is not the same as being cleared to watch a colleague's screen.
 *
 * Kept in its own table rather than as a column on `users`, so the module
 * stays self-contained and the existing users table is untouched. No row
 * means no access, so nobody gains a capability by this migration running.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_monitoring_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->boolean('can_view_screenshots')->default(false);
            $table->boolean('can_view_live_screen')->default(false);
            $table->boolean('can_manage_devices')->default(false);
            $table->boolean('can_manage_settings')->default(false);

            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_monitoring_permissions');
    }
};
