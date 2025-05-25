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
            $table->uuid('token')->unique();
            $table->foreignId('contract_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->enum('role', ['submitter','supervisor']);
            $table->date('start_date')->nullable();        // ← new
            $table->date('due_date')->nullable();          // ← new
            $table->foreignId('supervisor_id')             // ← new
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            $table->foreignId('invited_by')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->boolean('consumed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
    }
};
