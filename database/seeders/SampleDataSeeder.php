<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Active Agent
        $activeTenant = Tenant::create([
            'name' => 'Active Travels',
            'email' => 'active@example.com',
            'status' => 'active',
            'joined_at' => now()->subMonths(6),
            'expires_at' => now()->addMonths(6),
            'subscription_amount' => 500,
            'currency' => 'ILS',
            'language' => 'he',
        ]);
        User::create([
            'name' => 'Active Agent',
            'email' => 'active@agent.com',
            'password' => Hash::make('password'),
            'role' => 'agent',
            'tenant_id' => $activeTenant->id,
            'language' => 'he',
        ]);

        // 2. Expiring Soon Agent (Less than 1 month)
        $expiringTenant = Tenant::create([
            'name' => 'Expiring Soon Ltd',
            'email' => 'expiring@example.com',
            'status' => 'active',
            'joined_at' => now()->subYear(),
            'expires_at' => now()->addDays(15),
            'subscription_amount' => 300,
            'currency' => 'USD',
            'language' => 'en',
        ]);
        User::create([
            'name' => 'Expiring Agent',
            'email' => 'expiring@agent.com',
            'password' => Hash::make('password'),
            'role' => 'agent',
            'tenant_id' => $expiringTenant->id,
            'language' => 'en',
        ]);

        // 3. Suspended Agent
        $suspendedTenant = Tenant::create([
            'name' => 'Suspended Agency',
            'email' => 'suspended@example.com',
            'status' => 'suspended',
            'joined_at' => now()->subMonths(2),
            'expires_at' => now()->subDays(5),
            'subscription_amount' => 0,
            'currency' => 'ILS',
            'language' => 'he',
        ]);
        User::create([
            'name' => 'Suspended Agent',
            'email' => 'suspended@agent.com',
            'password' => Hash::make('password'),
            'role' => 'agent',
            'tenant_id' => $suspendedTenant->id,
            'language' => 'he',
        ]);
    }
}
