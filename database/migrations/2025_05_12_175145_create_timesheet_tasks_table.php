<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTimesheetTasksTable extends Migration
{
    public function up()
    {
        Schema::create('timesheet_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('details')->nullable();
            $table->date('start_date');
            $table->date('due_date');
            // default to 'submitter'
            $table->enum('role',['submitter','supervisor'])->default('submitter');
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('timesheet_tasks');
    }
}
