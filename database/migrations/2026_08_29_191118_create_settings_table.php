<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            // Dot-keyed overrides for config/agent.php - "name", "office.street",
            // "social.facebook". The config file stays the source of defaults.
            // 191, not the 255 default: utf8mb4 puts a VARCHAR(255) primary key at
            // 1020 bytes, over the 767-byte InnoDB index limit on older MariaDB.
            $table->string('key', 191)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
