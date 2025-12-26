<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tenant;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Super Admin
        if (!User::where('email', 'admin@admin.com')->exists()) {
            User::create([
                'name' => 'Super Admin',
                'email' => 'admin@admin.com',
                'password' => Hash::make('password'),
                'role' => 'super_admin',
                'tenant_id' => null,
            ]);
        }

        // Create Demo Tenant
        $tenant = Tenant::firstOrCreate(
            ['email' => 'agent@demo.com'],
            [
                'name' => 'Demo Agency',
                'status' => 'active',
                'receipt_prefix' => 'DEMO',
            ]
        );

        // Create Agent User
        if (!User::where('email', 'agent@demo.com')->exists()) {
            User::create([
                'name' => 'Demo Agent',
                'email' => 'agent@demo.com',
                'password' => Hash::make('password'),
                'role' => 'agent',
                'tenant_id' => $tenant->id,
            ]);
        }
    }
}
