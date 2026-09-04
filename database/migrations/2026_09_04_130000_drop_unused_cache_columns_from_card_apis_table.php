<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Both columns were written on every card-api-widget mount and read
     * nowhere - the schema advertised a cache that did not exist, while every
     * dashboard load still fired live, blocking, un-TTL'd HTTP at every
     * configured service. Dropping them rather than wiring up a read path:
     * card_apis has no refresh-interval concept to hang a TTL off (unlike
     * card_outputs.refresh_interval_seconds), so a real cache is a feature to
     * design, not a column to start reading.
     */
    public function up(): void
    {
        Schema::table('card_apis', function (Blueprint $table) {
            $table->dropColumn(['cached_data', 'last_fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::table('card_apis', function (Blueprint $table) {
            $table->json('cached_data')->nullable();
            $table->timestamp('last_fetched_at')->nullable();
        });
    }
};
