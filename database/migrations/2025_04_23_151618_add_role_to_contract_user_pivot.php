<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRoleToContractUserPivot extends Migration
{
    public function up()
    {
        Schema::table('contract_user', function (Blueprint $table) {
            $table->enum('role', ['owner', 'submitter', 'supervisor'])
                  ->default('submitter')
                  ->after('user_id');
        });
    }

    public function down()
    {
        Schema::table('contract_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
}
