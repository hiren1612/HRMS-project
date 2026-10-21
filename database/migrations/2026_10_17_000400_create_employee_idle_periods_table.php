<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productivity module — each stretch of inactivity, start to finish.
 *
 * The totals on the session answer "how much idle time today"; this table
 * answers "when, and for how long", which is what the daily timeline needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_idle_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('employee_tracking_sessions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('seconds')->default(0);

            $table->timestamps();

            $table->index(['employee_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_idle_periods');
    }
};
