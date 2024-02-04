<?php

namespace Database\Seeders;

use App\User;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeders.
     *
     * @return void
     */
    public function run()
    {
      User::factory()->count(3)->create();
    }
}
