<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The facts a Dubai listing is expected to carry but a generic property
     * schema does not: what is next door, what it costs to run, and the legal
     * identifiers a buyer asks for before they agree to view.
     *
     * Every column is nullable. Listings that predate this - and any property
     * where the owner did not supply the detail - simply do not show the row,
     * rather than showing "not recorded" five times.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Landmarks, as [{type, name, distance}] - metro, mall, school,
            // beach, hospital, airport. A list, not columns, because a listing
            // has an unpredictable number of them and the types will grow.
            $table->json('nearby')->nullable()->after('features');

            $table->string('view_type', 60)->nullable()->after('nearby');
            $table->string('furnishing', 20)->nullable()->after('view_type');
            $table->string('completion', 20)->nullable()->after('furnishing');
            $table->string('handover', 40)->nullable()->after('completion');
            $table->string('ownership', 20)->nullable()->after('handover');

            // AED per square foot per year, the way Dubai quotes it.
            $table->unsignedInteger('service_charge')->nullable()->after('ownership');

            $table->string('payment_terms', 120)->nullable()->after('service_charge');

            // The permit for the advert itself (DLD / Trakhees). Required on
            // portals, and the one number that proves a listing is real.
            $table->string('permit_number', 40)->nullable()->after('payment_terms');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'nearby',
                'view_type',
                'furnishing',
                'completion',
                'handover',
                'ownership',
                'service_charge',
                'payment_terms',
                'permit_number',
            ]);
        });
    }
};
