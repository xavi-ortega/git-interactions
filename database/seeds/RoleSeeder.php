<?php

use App\Role;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
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
