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
        Schema::create('card_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('command');
            $table->text('last_output')->nullable();
            $table->integer('last_exit_code')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_outputs');
    }
};
