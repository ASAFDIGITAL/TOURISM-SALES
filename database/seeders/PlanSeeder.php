<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Plan::defaultBlueprints() as $blueprint) {
            Plan::query()->firstOrCreate(
                ['slug' => $blueprint['slug']],
                $blueprint,
            );
        }
    }
}
