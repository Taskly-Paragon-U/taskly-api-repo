<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_user', function (Blueprint $table) {
            // these are the dates that the contract is effective for this user
            $table->date('start_date')->nullable()->after('role');
            $table->date('due_date')->nullable()->after('start_date');
            // supervisor (another user) can oversee multiple submitters
            $table->foreignId('supervisor_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('contract_user', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropColumn(['supervisor_id', 'due_date', 'start_date']);
        });
    }
};
