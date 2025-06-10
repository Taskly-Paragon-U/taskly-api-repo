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
        // Check if the label column exists before trying to modify it
        if (Schema::hasColumn('contract_user', 'label')) {
            Schema::table('contract_user', function (Blueprint $table) {
                $table->enum('label', ['TA', 'AA', 'Intern'])->nullable()->change();
            });
        }
        // If column doesn't exist, skip this migration since we're using submitter_labels table
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if the label column exists before trying to modify it
        if (Schema::hasColumn('contract_user', 'label')) {
            Schema::table('contract_user', function (Blueprint $table) {
                $table->enum('label', ['TA', 'AA', 'Intern'])->default('TA')->change();
            });
        }
    }
};