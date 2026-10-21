<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productivity module — who looked at what.
 *
 * Viewing a screenshot or starting a live session is itself an event worth
 * recording. Rows survive the deletion of the actor or the subject, because
 * an audit trail that vanishes when an account is removed is not one.
 *
 * Separate from the existing `activities` table so the HRMS audit trail is
 * not diluted with monitoring traffic, and so retention can differ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_monitoring_audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();

            // screenshot.viewed, screenshot.downloaded, live.requested,
            // live.started, live.ended, device.approved, settings.updated …
            $table->string('action', 60);

            $table->nullableMorphs('subject');

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('context')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['employee_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_monitoring_audit_logs');
    }
};
