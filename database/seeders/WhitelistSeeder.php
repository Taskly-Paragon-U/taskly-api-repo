<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WhitelistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        DB::table('whitelisted_emails')->insert([
            ['email' => 'hhuy@paragon.iu.edu.kh'],
        ]);
    }
}
