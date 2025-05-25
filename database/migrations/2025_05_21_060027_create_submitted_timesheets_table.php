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
            $table->foreignId('contract_id')  ;      
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            
            $table->string('file_path');
            $table->string('file_name');
            $table->timestamp('submitted_at')->useCurrent();
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
