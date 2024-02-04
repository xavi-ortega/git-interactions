<?php

namespace Database\Seeders;

use App\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeders.
     *
     * @return void
     */
    public function run()
    {
        Role::insert([
            [
                'name' => 'free',
                'description' => 'Free user'
            ],
            [
                'name' => 'enterprise',
                'description' => 'Enterprise user'
            ]
        ]);
    }
}
