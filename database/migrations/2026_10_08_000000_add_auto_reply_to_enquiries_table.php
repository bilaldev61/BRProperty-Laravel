<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The AI-written reply that was emailed, word for word. Null means the plain
 * template confirmation went instead (or nothing went). The agent reads it in
 * the inbox, so they always know exactly what the client was told.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->text('auto_reply')->nullable()->after('confirmation_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn('auto_reply');
        });
    }
};
