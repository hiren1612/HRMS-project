<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productivity module — a record of every live screen view.
 *
 * Every attempt is written here, including ones that were declined or that
 * failed, so "who watched whose screen, when, and for how long" can always
 * be answered. employee_notified_at records when the agent confirmed it had
 * shown the on-screen indicator: viewing without that confirmation is a bug,
 * not a feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_live_monitoring_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('employee_tracking_devices')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();

            $table->timestamp('requested_at');
            $table->timestamp('employee_notified_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            // requested | notified | active | ended | declined | failed
            $table->string('status', 20)->default('requested');

            // stopped_by_admin | stopped_by_employee | disconnected | timeout
            $table->string('end_reason', 30)->nullable();

            $table->string('initiator_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'requested_at']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_live_monitoring_sessions');
    }
};
