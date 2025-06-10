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
        Schema::create('submitter_role_supervisor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')
                  ->constrained('contracts')
                  ->onDelete('cascade');
            $table->foreignId('submitter_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->enum('label', ['TA','AA','Intern']);
            $table->foreignId('supervisor_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->timestamps();

            // Prevent duplicate mappings of the same submitter–label–supervisor in one contract
            $table->unique(
                ['contract_id','submitter_id','label','supervisor_id'],
                'sub_role_sup_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submitter_role_supervisor');
    }
};
