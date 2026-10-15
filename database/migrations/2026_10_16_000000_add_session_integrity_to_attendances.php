<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * QA H1 / N2: a forgotten clock-out counted the whole gap as work —
     * 97h and 2,879h rows exist. These columns make an auto-closed or
     * implausible session visible so HR can correct it, rather than it
     * silently inflating every total.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // manual | auto | hr
            $table->string('closed_by', 20)->nullable()->after('break_minutes');
            $table->text('correction_note')->nullable()->after('closed_by');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['closed_by', 'correction_note']);
        });
    }
};
