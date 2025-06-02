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
        Schema::create('submitted_timesheets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')
                  ->constrained('timesheet_tasks')
                  ->onDelete('cascade');

            $table->foreignId('contract_id')
                  ->constrained('contracts')       // ← if you have a contracts table
                  ->onDelete('cascade');

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->string('file_path');
            $table->string('file_name');
            $table->timestamp('submitted_at')->useCurrent();

            // REVIEW FIELDS
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // Add rejection_reason so that “rejected” can store a text reason
            $table->string('rejection_reason')->nullable();

            // Foreign key constraint for supervisor_id
            $table->foreign('supervisor_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submitted_timesheets');
    }
};
