<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productivity module — a PC running the desktop agent.
 *
 * A device is registered on first login from the agent and starts as
 * "pending": nothing is tracked until an administrator approves it. That is
 * deliberate, so installing the agent is never enough on its own to begin
 * monitoring somebody.
 *
 * Entirely new table. No existing table is touched by this module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_tracking_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // Generated once by the agent at install and kept in its local
            // store, so a reinstall on the same PC re-registers rather than
            // creating a duplicate device.
            $table->uuid('device_uuid')->unique();

            $table->string('device_name');
            $table->string('platform', 60)->nullable();
            $table->string('agent_version', 20)->nullable();

            // pending | approved | revoked
            $table->string('status', 20)->default('pending');

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_tracking_devices');
    }
};
