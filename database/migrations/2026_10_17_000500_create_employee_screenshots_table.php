<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productivity module — captured screenshots.
 *
 * Only the location is stored here. The image itself goes to the private
 * "monitoring" disk, which has no public URL and is never symlinked into
 * public/. Images are served through an authorised controller that records
 * who looked at what, so a leaked path cannot expose anyone's screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_screenshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('employee_tracking_devices')->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('employee_tracking_sessions')->nullOnDelete();

            $table->timestamp('captured_at');

            $table->string('disk', 30)->default('monitoring');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();

            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('bytes')->default(0);

            // The state the employee was in when it was taken. Captures are
            // skipped while idle or paused, so this is "active" in normal
            // operation and anything else is worth questioning.
            $table->string('status_at_capture', 20)->default('active');

            $table->string('checksum', 64)->nullable();

            // Retention deletes rows and files together; soft delete gives a
            // grace window and keeps a reference if one is queried meanwhile.
            $table->softDeletes();
            $table->timestamps();

            $table->index(['employee_id', 'captured_at']);

            // A retried upload after a dropped connection must not create a
            // second copy of the same capture.
            $table->unique(['device_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_screenshots');
    }
};
