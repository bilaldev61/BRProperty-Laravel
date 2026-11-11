<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per request the model was sent.
 *
 * A single visitor message can cost up to five of these, because every tool
 * round re-sends the whole prompt and is billed on its own. `cached_tokens` is
 * the reason the table exists: `chat_messages.tokens` holds only the total, so
 * without this there is no way to tell a prompt that is being reused from one
 * that is paid for in full every round.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_message_id')->nullable()->constrained()->cascadeOnDelete();
            // 0 is the first request of the turn; each tool round adds one.
            $table->unsignedTinyInteger('round')->default(0);
            $table->string('model', 120);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            // Comma-separated, in the order the model asked for them.
            $table->string('tools', 255)->nullable();
            $table->timestamps();

            $table->index(['chat_conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_usage');
    }
};
