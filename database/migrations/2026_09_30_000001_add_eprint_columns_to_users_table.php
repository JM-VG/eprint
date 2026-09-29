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
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('student_id')->nullable()->unique()->after('id');
            $table->string('username')->nullable()->unique()->after('student_id');
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('role')->default('student')->index()->after('password');
            $table->string('status')->default('pending')->index()->after('role');
            $table->boolean('must_change_password')->default(false)->after('status');
            $table->foreignId('approved_by')->nullable()->after('must_change_password')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropUnique(['student_id']);
            $table->dropUnique(['username']);
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'student_id', 'username', 'first_name', 'last_name', 'role', 'status',
                'must_change_password', 'approved_at', 'rejection_reason',
            ]);
            $table->string('email')->nullable(false)->change();
        });
    }
};
