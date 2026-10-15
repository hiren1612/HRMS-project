<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * QA M3: a rejection needs a reason the employee can read.
     * QA M4: an employee needs to be able to withdraw a pending request,
     *        which the overlap message (H9) already tells them to do.
     */
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->text('decision_note')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn('decision_note');
        });
    }
};
