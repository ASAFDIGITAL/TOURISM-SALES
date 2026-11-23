<?php

namespace App\Console\Commands;

use App\Services\TrialOnboardingService;
use Illuminate\Console\Command;

class SendTrialOnboardingEmails extends Command
{
    protected $signature = 'app:send-trial-onboarding-emails';

    protected $description = 'Send automated trial onboarding and conversion drip emails (Day 0, Day 7, Day 11, Day 13)';

    public function handle(TrialOnboardingService $service): int
    {
        $sent = $service->processAllTrialTenants();
        $this->info("Sent {$sent} trial onboarding email(s).");

        return self::SUCCESS;
    }
}
