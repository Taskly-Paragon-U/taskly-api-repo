<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submitter_supervisor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->onDelete('cascade');
            $table->foreignId('submitter_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('supervisor_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            // Prevent duplicate relationships with a shorter constraint name
            $table->unique(['contract_id', 'submitter_id', 'supervisor_id'], 'sub_sup_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submitter_supervisor');
    }
};