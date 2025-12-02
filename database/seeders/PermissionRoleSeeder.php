<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Buat Permission Dasar
        $permissions = [
            // Dashboard
            'view dashboard',
            
            // Berita
            'view berita',
            'create berita',
            'edit berita',
            'delete berita',
            
            // User
            'view user',
            'create user',
            'edit user',
            'delete user',
            
            // Role & Permission
            'view role',
            'create role',
            'edit role',
            'delete role',
            
            // Profil Masjid
            'view profil masjid',
            'edit profil masjid',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 2. Buat Role
        $superAdmin = Role::firstOrCreate(['name' => 'SuperAdmin']);
        $editor = Role::firstOrCreate(['name' => 'Editor Berita']);
        $viewer = Role::firstOrCreate(['name' => 'Viewer']);

        // 3. Assign Permission ke Role
        // SuperAdmin: Akses SEMUA
        $superAdmin->givePermissionTo(Permission::all());

        // Editor Berita: Hanya berita
        $editor->givePermissionTo([
            'view dashboard',
            'view berita',
            'create berita',
            'edit berita',
        ]);

        // Viewer: Hanya lihat
        $viewer->givePermissionTo([
            'view dashboard',
            'view berita',
            'view profil masjid',
        ]);

        // 4. Buat SuperAdmin User (jika belum ada)
        $superUser = \App\Models\User::firstOrCreate(
            ['email' => 'super@emasjid.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('password')
            ]
        );
        $superUser->assignRole('SuperAdmin');
    }
}