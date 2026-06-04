<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin123'),
            // 'role' => 'admin',
        ]);

        if ($admin) {
            $admin->assignRole('admin');
        }
    }
}
