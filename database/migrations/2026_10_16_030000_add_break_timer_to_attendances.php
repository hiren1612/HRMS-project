<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * QA U10: a break had to be typed in as a number of minutes, which meant
     * guessing it after the fact and meant nothing on screen showed that
     * somebody was on a break right now. This column holds the moment the
     * current break began; it is null whenever nobody is on a break.
     *
     * break_minutes keeps its meaning — total break time for the session —
     * so existing rows, the productive-time formula and HR corrections are
     * unaffected.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->timestamp('break_started_at')->nullable()->after('break_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('break_started_at');
        });
    }
};
