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
        Schema::create('station_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('close_reason')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'closed_at', 'last_seen_at']);
            $table->index(['user_id', 'closed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('station_sessions');
    }
};
