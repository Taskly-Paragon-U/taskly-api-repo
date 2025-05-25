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
        Schema::create('timesheet_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('details')->nullable();
            $table->date('start_date');
            $table->date('due_date');
            $table->enum('role', ['submitter','supervisor'])->default('submitter');
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();

            // all template fields in one place
            $table->string('template_url')->nullable();
            $table->string('template_file')->nullable();
            $table->string('template_file_name')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timesheet_tasks');
    }
};
