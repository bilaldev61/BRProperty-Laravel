<?php

use App\Models\Enquiry;
use App\Support\EnquiryPriority;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the inbox into a work queue.
 *
 * `read_at` only ever said whether the agent had opened a message, which is not
 * the same as having dealt with it. `status` tracks the actual lifecycle, and
 * `priority` is a stored copy of the rules in EnquiryPriority so the list can be
 * sorted and filtered in SQL. Priority inputs (type, timeframe, phone) never
 * change after an enquiry is submitted, so the stored value cannot drift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->string('status')->default(Enquiry::STATUS_NEW)->after('details');
            $table->string('priority')->default(EnquiryPriority::COLD)->after('status');
            $table->timestamp('follow_up_at')->nullable()->after('priority');

            $table->index(['status', 'priority']);
            $table->index('follow_up_at');
        });

        // Backfill: existing rows were only ever read/unread. Anything already
        // opened is treated as replied, so the queue does not open full of work
        // the agent has in fact already done.
        Enquiry::query()->cursor()->each(function (Enquiry $enquiry) {
            $enquiry->forceFill([
                'priority' => EnquiryPriority::for($enquiry),
                'status'   => $enquiry->read_at ? Enquiry::STATUS_REPLIED : Enquiry::STATUS_NEW,
            ])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropIndex(['status', 'priority']);
            $table->dropIndex(['follow_up_at']);
            $table->dropColumn(['status', 'priority', 'follow_up_at']);
        });
    }
};
