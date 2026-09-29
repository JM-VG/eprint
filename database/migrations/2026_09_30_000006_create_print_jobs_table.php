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
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('claim_code', 7)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('source')->default('online');
            $table->string('original_name');
            $table->string('stored_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('file_type', 20);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('page_count')->nullable();
            $table->string('page_range')->nullable();
            $table->unsignedInteger('copies')->default(1);
            $table->boolean('duplex')->default(false);
            $table->unsignedInteger('sets')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('estimated_total', 10, 2)->nullable();
            $table->decimal('final_total', 10, 2)->nullable();
            $table->unsignedInteger('sheets_used')->nullable();
            $table->string('status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('unpaid');
            $table->text('reject_reason')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('files_deleted_at')->nullable();
            $table->timestamps();

            $table->index(['location_id', 'status', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
