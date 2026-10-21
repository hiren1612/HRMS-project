<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productivity module — admin-configurable settings, one row per key.
 *
 * Key/value rather than one wide row, so adding a setting later is a write
 * rather than a schema change. Defaults live in config/monitoring.php; a row
 * here exists only once an administrator has changed something.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_tracking_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->json('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_tracking_settings');
    }
};
