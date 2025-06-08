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
        Schema::create('submitter_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->onDelete('cascade');
            $table->foreignId('submitter_id')->constrained('users')->onDelete('cascade');
            $table->enum('label', ['TA', 'AA', 'Intern']);
            $table->timestamps();
            
            // Ensure a submitter can't have the same label twice in the same contract
            $table->unique(['contract_id', 'submitter_id', 'label']);
            
            // Add indexes for better performance
            $table->index(['contract_id', 'submitter_id']);
            $table->index(['submitter_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submitter_labels');
    }
};