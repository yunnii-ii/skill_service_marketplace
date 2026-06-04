<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::firstOrCreate(['name'=> 'buyer','guard_name'=>'api']);
        Role::firstOrCreate(['name'=>'seller', 'guard_name'=>'api']);
        Role::firstOrcreate(['name' => 'admin', 'guard_name'=> 'api']);

    }
}
