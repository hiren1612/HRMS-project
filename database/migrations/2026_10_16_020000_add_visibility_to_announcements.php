<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * QA L2 / L1: announcements had no management screen, so a stale notice
     * ("Payroll date moved") stayed on every dashboard with no way to remove
     * it. These columns let HR unpublish a notice or give it an end date, so
     * an announcement about a one-off event drops off by itself.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('is_published')->default(true)->after('body');
            $table->date('expires_at')->nullable()->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['is_published', 'expires_at']);
        });
    }
};
