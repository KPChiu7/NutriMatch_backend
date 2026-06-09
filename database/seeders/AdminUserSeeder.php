<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@nutrimatch.ph'],
            [
                'role'               => 'admin',
                'first_name'         => 'Admin',
                'last_name'          => 'NutriMatch',
                'password'           => Hash::make('admin1234'),
                'is_active'          => true,
                'email_verified_at'  => now(),
            ]
        );

        $this->command->info('Admin user seeded: admin@nutrimatch.ph / admin1234');
        $this->command->warn('IMPORTANT: Change the admin password immediately after first login!');
    }
}
