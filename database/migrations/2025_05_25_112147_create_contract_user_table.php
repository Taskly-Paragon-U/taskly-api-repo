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

            // Link to contracts table
            $table->foreignId('contract_id')
                  ->constrained('contracts')
                  ->onDelete('cascade');

            // Link to users table
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            // Role in the contract
            $table->enum('role', ['owner','submitter','supervisor'])
                  ->default('submitter');

            // Effective dates for this user on the contract
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();

            // Optional supervisor assignment
            $table->foreignId('supervisor_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_user');
    }
};
