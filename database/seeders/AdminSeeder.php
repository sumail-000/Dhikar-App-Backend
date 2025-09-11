<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default super admin
        Admin::updateOrCreate(
            ['email' => 'admin@wered.com'],
            [
                'username' => 'Super Admin',
                'email' => 'admin@wered.com',
                'password' => Hash::make('admin123'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );


        $this->command->info('Admin users created successfully!');
        $this->command->info('Super Admin: admin@wered.com / admin123');
    }
}