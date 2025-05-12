<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class DropWhitelistedEmails extends Migration
{
    public function up()
    {
        Schema::dropIfExists('whitelisted_emails');
    }

    public function down()
    {
        // If you ever roll back, you can re-create with just an `email` column:
        Schema::create('whitelisted_emails', function ($table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamps();
        });
    }
}
