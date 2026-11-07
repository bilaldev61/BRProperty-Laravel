<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What each assistant reply cost.
 *
 * `answered_by` says whether the model was called at all - 'ai', 'limit' (the
 * hand-off at the message cap) or 'rule:<kind>' for a reply built straight
 * from the database. `tokens` is what the model used for an 'ai' reply. Read
 * together they show how much of the chat the rules are absorbing for free.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('answered_by', 40)->nullable()->after('content');
            $table->unsignedInteger('tokens')->nullable()->after('answered_by');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['answered_by', 'tokens']);
        });
    }
};
