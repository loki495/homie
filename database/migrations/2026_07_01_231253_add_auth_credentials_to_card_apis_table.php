<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('card_apis', function (Blueprint $table) {
            $table->string('auth_type')->default('api_key')->after('provider');
            $table->string('username')->nullable()->after('api_key');
            $table->text('password')->nullable()->after('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_apis', function (Blueprint $table) {
            $table->dropColumn(['auth_type', 'username', 'password']);
        });
    }
};
