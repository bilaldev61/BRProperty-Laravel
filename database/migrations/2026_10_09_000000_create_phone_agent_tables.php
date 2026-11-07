<?php

use App\Models\Enquiry;
use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The phone agent: calls, the qualification of the leads they create, and
 * the viewings they book.
 *
 * A call is stored as the voice provider reported it, keyed by its call id so
 * a webhook delivered twice updates one row instead of adding a second. Leads
 * stay in `enquiries` - a caller is one more way in, alongside the forms and
 * the chat - and gain structured requirements, a qualification score, and a
 * normalised phone number so a repeat caller is recognised.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->string('phone_normalized', 20)->nullable()->after('phone');
            $table->json('requirements')->nullable()->after('details');
            $table->json('qualification')->nullable()->after('requirements');

            $table->index('phone_normalized');
        });

        // Existing leads become recognisable by phone too.
        Enquiry::query()->whereNotNull('phone')->cursor()->each(function (Enquiry $enquiry) {
            $enquiry->forceFill(['phone_normalized' => Phone::normalize($enquiry->phone)])->saveQuietly();
        });

        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 20)->default('retell');
            $table->string('provider_call_id')->unique();
            $table->string('agent_id')->nullable();
            $table->string('direction', 10)->nullable();       // inbound | outbound
            $table->string('from_number', 20)->nullable();
            $table->string('to_number', 20)->nullable();
            $table->string('status', 30)->nullable();          // provider call_status
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('disconnection_reason', 60)->nullable();
            $table->text('transcript')->nullable();
            $table->text('summary')->nullable();
            $table->string('sentiment', 20)->nullable();
            $table->boolean('in_voicemail')->default(false);
            $table->json('analysis')->nullable();              // the provider's custom analysis fields, as sent
            $table->string('recording_url', 1000)->nullable();
            $table->unsignedInteger('cost_cents')->nullable();
            $table->json('outcome')->nullable();               // what was decided after the call, returned again on a repeat delivery
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('started_at');
            $table->index('from_number');
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('call_id')->nullable()->constrained()->nullOnDelete();
            // dateTime rather than timestamp: MySQL gives a NOT NULL timestamp
            // column an implicit default (and MariaDB rejects that under
            // NO_ZERO_DATE), and appointments belong on a calendar, not on the
            // 2038 edge that TIMESTAMP runs up against.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('requested'); // requested | confirmed | completed | cancelled | no_show
            $table->string('source', 16)->default('voice');      // voice | admin
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('calls');

        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropIndex(['phone_normalized']);
            $table->dropColumn(['phone_normalized', 'requirements', 'qualification']);
        });
    }
};
