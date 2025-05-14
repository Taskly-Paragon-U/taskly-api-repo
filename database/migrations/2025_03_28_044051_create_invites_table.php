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
        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('contract_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->enum('role', ['submitter','supervisor']);
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade');
            $table->boolean('consumed')->default(false);
            $table->string('major')->nullable();
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

        });        
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invites');
    }
};
