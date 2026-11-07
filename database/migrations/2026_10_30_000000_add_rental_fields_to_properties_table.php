<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A property keeps its existing sale identity and gains an independent rental
     * one, so a single listing can be for sale, for rent, or both at once. The
     * rental fields are additive and defaulted, leaving current rows untouched.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->boolean('is_for_rent')->default(false)->after('days_on_market');
            $table->unsignedInteger('rent_price')->nullable()->after('is_for_rent');      // monthly rent, PKR
            $table->string('rent_price_label')->nullable()->after('rent_price');           // "Rent negotiable"
            $table->string('rent_period', 8)->default('month')->after('rent_price_label');  // month | year
            $table->string('rent_status', 16)->default('available')->after('rent_period');  // available | let_agreed | let

            $table->index(['is_for_rent', 'rent_status']);
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['is_for_rent', 'rent_status']);
            $table->dropColumn(['is_for_rent', 'rent_price', 'rent_price_label', 'rent_period', 'rent_status']);
        });
    }
};
