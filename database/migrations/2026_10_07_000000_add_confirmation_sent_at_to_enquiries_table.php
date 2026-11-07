<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the "we got your enquiry" email reached the mail server. Null means it
 * was never sent - no email address, sending switched off, or it failed - and
 * the inbox offers to send it again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->timestamp('confirmation_sent_at')->nullable()->after('follow_up_at');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn('confirmation_sent_at');
        });
    }
};
