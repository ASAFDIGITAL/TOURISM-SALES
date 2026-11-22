<?php

namespace App\Console\Commands;

use App\Services\ReminderRuleEngine;
use Illuminate\Console\Command;

class RunReminderRules extends Command
{
    protected $signature = 'app:run-reminder-rules';

    protected $description = 'Evaluate tenant smart reminder rules (trips, flights, and payments) and dispatch alerts';

    public function handle(ReminderRuleEngine $engine): int
    {
        $count = $engine->processAllRules();
        $this->info("Dispatched {$count} automated rule reminders.");

        return self::SUCCESS;
    }
}
