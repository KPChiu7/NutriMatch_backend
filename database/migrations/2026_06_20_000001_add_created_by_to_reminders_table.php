<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds created_by to reminders so RND-created vs client-created
     * reminders are distinguishable on the row itself.
     *
     * Nullable + ON DELETE SET NULL: if the creating user's account is
     * later deleted, the reminder itself is preserved (it still belongs
     * to client_id, which has its own separate CASCADE) — only the
     * "who created it" attribution is lost, not the reminder.
     */
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->unsignedInteger('created_by')->nullable()->after('client_id');

            $table->foreign('created_by')
                ->references('id')->on('users')
                ->onDelete('set null');

            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
