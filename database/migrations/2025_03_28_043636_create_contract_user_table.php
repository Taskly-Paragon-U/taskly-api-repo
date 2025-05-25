<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();                // one-time lookup key
            $table->foreignId('contract_id')
                  ->constrained()->onDelete('cascade');
            $table->string('email');                        // invited email
            $table->enum('role', ['submitter','supervisor']);

            // → NEW: if this is a submitter invite, store their window:
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();

            // → NEW: and which supervisor they will be assigned to:
            $table->foreignId('supervisor_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            $table->foreignId('invited_by')
                  ->constrained('users')->onDelete('cascade');
            $table->boolean('consumed')->default(false);    // mark when claimed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
    }
};
