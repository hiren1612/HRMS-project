<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productivity module — activity in fixed buckets (one row per minute).
 *
 * Raw input events are NOT stored: a busy day would be millions of rows and
 * none of it would tell you anything a per-minute summary does not. Only
 * counts are kept.
 *
 * key_events is a COUNT of keypresses. No key codes and no typed characters
 * are collected anywhere in this module, by design.
 *
 * Not to be confused with the existing `activities` table, which is the HRMS
 * audit trail and is untouched by this module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('employee_tracking_sessions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // The agent generates this per bucket. A retry after a dropped
            // connection re-sends the same uuid, so a duplicate submission
            // is rejected by the database rather than double-counted.
            $table->uuid('client_uuid')->unique();

            // datetime, not timestamp: MySQL allows only one implicitly
            // defaulted TIMESTAMP per table and rejects the second under
            // strict mode. Both ends of a bucket are required, so neither
            // can be made nullable to dodge that.
            $table->dateTime('bucket_started_at');
            $table->dateTime('bucket_ended_at');

            $table->unsignedSmallInteger('active_seconds')->default(0);
            $table->unsignedSmallInteger('idle_seconds')->default(0);

            $table->unsignedInteger('mouse_events')->default(0);
            $table->unsignedInteger('key_events')->default(0);

            // active | idle | paused
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->index(['employee_id', 'bucket_started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_activity_logs');
    }
};
