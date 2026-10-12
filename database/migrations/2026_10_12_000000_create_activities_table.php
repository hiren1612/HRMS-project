<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail of who did what, and when. Written from here on; nothing
     * before this migration can be reconstructed.
     */
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            // Null when the actor's account is later deleted — the trail survives.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Machine-readable, e.g. "leave.approved", "auth.login".
            $table->string('action', 60);
            $table->string('description');
            // What was acted on, when there is a record behind it.
            $table->nullableMorphs('subject');
            $table->string('url')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
