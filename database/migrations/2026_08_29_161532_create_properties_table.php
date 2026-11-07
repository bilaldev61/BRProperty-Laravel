<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('status')->default('for_sale'); // for_sale | under_offer | sold
            $table->string('type')->default('house');      // house | apartment | townhouse | land | acreage

            $table->unsignedInteger('price')->nullable();
            $table->string('price_label')->nullable();      // "Offers over $1.2M", "Contact Agent"
            $table->unsignedInteger('sold_price')->nullable();
            $table->date('sold_at')->nullable();
            $table->unsignedSmallInteger('days_on_market')->nullable();

            $table->string('address');
            $table->string('suburb');
            $table->string('state', 16)->default(config('agent.office.state'));
            $table->string('postcode', 8);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->unsignedTinyInteger('bedrooms')->default(0);
            $table->unsignedTinyInteger('bathrooms')->default(0);
            $table->unsignedTinyInteger('carspaces')->default(0);
            $table->unsignedInteger('land_size')->nullable();  // m2
            $table->unsignedInteger('floor_size')->nullable(); // m2

            $table->json('features')->nullable();
            $table->string('inspection_times')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->string('meta_description')->nullable();

            $table->timestamps();

            $table->index(['status', 'is_published']);
            $table->index('suburb');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
