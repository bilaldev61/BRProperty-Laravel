<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public property assistant.
 *
 * A conversation is one visitor's chat, keyed by an unguessable token held in
 * their session. It remembers the listing it started on, and the enquiry it
 * turned into if the visitor left their details - which is what lets the agent
 * read the whole chat from the inbox.
 *
 * Unanswered questions are what the assistant had to defer to the agent. They
 * are kept apart from the transcript because they are read the other way round:
 * not "what did this visitor ask" but "what do visitors keep asking that the
 * listings do not say".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('enquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('visitor_messages')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index('last_message_at');
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16); // user | assistant
            $table->text('content');
            $table->timestamps();
        });

        Schema::create('chat_unanswered_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('topic', 40);
            $table->string('question', 500);
            $table->timestamps();

            $table->index(['topic', 'created_at']);
        });

        Schema::table('enquiries', function (Blueprint $table) {
            // A chat lead often leaves only a phone number - in this market that
            // is the contact detail that matters, so email can no longer be
            // required at the database level. The website forms still require it.
            $table->string('email')->nullable()->change();
            $table->string('source', 16)->default('form')->after('type'); // form | chat
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn('source');
            $table->string('email')->nullable(false)->change();
        });

        Schema::dropIfExists('chat_unanswered_questions');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
