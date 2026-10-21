<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productivity module — one run of the desktop agent.
 *
 * Deliberately separate from the existing `attendances` table. The agent
 * never writes to attendance, so clock-in/clock-out, leave balances and
 * every existing report behave exactly as they do today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_tracking_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('employee_tracking_devices')->cascadeOnDelete();

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();

            // Running totals, kept in step as activity buckets arrive so the
            // dashboard never has to sum the whole log table.
            $table->unsignedInteger('active_seconds')->default(0);
            $table->unsignedInteger('idle_seconds')->default(0);

            // running | ended
            $table->string('status', 20)->default('running');

            // manual | logout | shutdown | stale
            $table->string('ended_reason', 30)->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'started_at']);
            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_tracking_sessions');
    }
};
