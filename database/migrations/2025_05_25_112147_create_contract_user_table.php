<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['owner','submitter','supervisor'])->default('submitter');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('label', ['TA','AA','Intern'])->nullable(); // Make nullable instead of default
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_user');
    }
};