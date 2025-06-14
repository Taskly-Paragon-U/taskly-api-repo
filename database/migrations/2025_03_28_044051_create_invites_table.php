<?php
// invites_table_migration.php
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
            $table->date('start_date')->nullable();        
            $table->date('due_date')->nullable();          
            $table->foreignId('supervisor_id')          
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            $table->foreignId('invited_by')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->boolean('consumed')->default(false);
            $table->enum('label', ['TA','AA','Intern'])->nullable(); // Already nullable with no default
            $table->text('labels_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
    }
};