<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Buat SuperAdmin
        $super = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@elantera.com',
            'password' => bcrypt('password')
        ]);
        $super->assignRole('SuperAdmin');
    }
}
