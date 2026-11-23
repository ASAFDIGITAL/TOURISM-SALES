<?php

namespace App\Services;

use App\Mail\TrialOnboardingMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;

class TrialOnboardingService
{
    public const STEP_DAY_0 = 'day_0';
    public const STEP_DAY_7 = 'day_7';
    public const STEP_DAY_11 = 'day_11';
    public const STEP_DAY_13 = 'day_13';

    public function sendStepIfNeeded(Tenant $tenant, string $step): bool
    {
        if (! $tenant->is_trial || $tenant->status !== 'active') {
            return false;
        }

        $sentSteps = is_array($tenant->trial_drip_steps_sent) ? $tenant->trial_drip_steps_sent : [];
        if (in_array($step, $sentSteps, true)) {
            return false;
        }

        $recipientEmail = $this->resolveOwnerEmail($tenant);
        if (! $recipientEmail) {
            return false;
        }

        $locale = in_array($tenant->language, ['he', 'ar', 'en'], true) ? $tenant->language : 'he';
        $originalLocale = App::getLocale();
        App::setLocale($locale);

        try {
            $payload = $this->buildStepContent($tenant, $step);

            Mail::to($recipientEmail)->send(new TrialOnboardingMail(
                tenant: $tenant,
                step: $step,
                subjectLine: $payload['subject'],
                heading: $payload['heading'],
                introText: $payload['intro'],
                highlights: $payload['highlights'],
                ctaLabel: $payload['cta_label'],
                ctaUrl: $payload['cta_url'],
                localeCode: $locale,
            ));

            $sentSteps[] = $step;
            $tenant->forceFill([
                'trial_drip_steps_sent' => array_values(array_unique($sentSteps)),
            ])->save();

            return true;
        } finally {
            App::setLocale($originalLocale);
        }
    }

    public function processAllTrialTenants(): int
    {
        $tenants = Tenant::with('plan')
            ->where('status', 'active')
            ->where('is_trial', true)
            ->whereNotNull('trial_ends_at')
            ->get();

        $sentCount = 0;

        foreach ($tenants as $tenant) {
            if ($tenant->isTrialExpired()) {
                continue;
            }

            $daysSinceStart = max(0, (int) $tenant->created_at->startOfDay()->diffInDays(now()->startOfDay(), false));
            $daysLeft = $tenant->getTrialDaysLeft();

            $step = null;
            if ($daysLeft <= 1) {
                $step = self::STEP_DAY_13;
            } elseif ($daysLeft <= 3) {
                $step = self::STEP_DAY_11;
            } elseif ($daysSinceStart >= 7) {
                $step = self::STEP_DAY_7;
            } elseif ($daysSinceStart === 0) {
                $step = self::STEP_DAY_0;
            }

            if ($step && $this->sendStepIfNeeded($tenant, $step)) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    private function resolveOwnerEmail(Tenant $tenant): ?string
    {
        if (filled($tenant->email) && filter_var($tenant->email, FILTER_VALIDATE_EMAIL)) {
            return $tenant->email;
        }

        $owner = User::where('tenant_id', $tenant->id)
            ->where('role', 'agent')
            ->orderByRaw("CASE WHEN agency_role = 'owner' THEN 0 ELSE 1 END")
            ->first();

        return ($owner && filter_var($owner->email, FILTER_VALIDATE_EMAIL)) ? $owner->email : null;
    }

    /**
     * @return array{subject: string, heading: string, intro: string, highlights: array<int, string>, cta_label: string, cta_url: string}
     */
    private function buildStepContent(Tenant $tenant, string $step): array
    {
        $baseUrl = rtrim((string) config('app.url', 'https://tourism.asafdigital.com'), '/');
        $tripsCount = $tenant->trips()->count();
        $customersCount = $tenant->customers()->count();
        $daysLeft = $tenant->getTrialDaysLeft();
        $planName = $tenant->plan?->getLocalizedName() ?? 'Pro';

        return match ($step) {
            self::STEP_DAY_0 => [
                'subject' => __('ui.drip_day0_subject', ['agency' => $tenant->name]),
                'heading' => __('ui.drip_day0_heading', ['agency' => $tenant->name]),
                'intro' => __('ui.drip_day0_intro', ['days' => max(1, $daysLeft ?: 14), 'plan' => $planName]),
                'highlights' => [
                    __('ui.drip_day0_h1'),
                    __('ui.drip_day0_h2'),
                    __('ui.drip_day0_h3'),
                ],
                'cta_label' => __('ui.drip_day0_cta'),
                'cta_url' => $baseUrl . '/agent',
            ],
            self::STEP_DAY_7 => [
                'subject' => __('ui.drip_day7_subject', ['agency' => $tenant->name]),
                'heading' => __('ui.drip_day7_heading'),
                'intro' => __('ui.drip_day7_intro', [
                    'trips' => $tripsCount,
                    'customers' => $customersCount,
                    'days' => $daysLeft,
                ]),
                'highlights' => [
                    __('ui.drip_day7_h1'),
                    __('ui.drip_day7_h2'),
                    __('ui.drip_day7_h3'),
                ],
                'cta_label' => __('ui.drip_day7_cta'),
                'cta_url' => $baseUrl . '/agent/reminder-rules',
            ],
            self::STEP_DAY_11 => [
                'subject' => __('ui.drip_day11_subject', ['days' => max(1, $daysLeft)]),
                'heading' => __('ui.drip_day11_heading', ['days' => max(1, $daysLeft)]),
                'intro' => __('ui.drip_day11_intro', ['agency' => $tenant->name, 'plan' => $planName]),
                'highlights' => [
                    __('ui.drip_day11_h1'),
                    __('ui.drip_day11_h2'),
                ],
                'cta_label' => __('ui.drip_upgrade_cta'),
                'cta_url' => $baseUrl . '/agent/billing-and-invoices',
            ],
            default => [
                'subject' => __('ui.drip_day13_subject'),
                'heading' => __('ui.drip_day13_heading'),
                'intro' => __('ui.drip_day13_intro', ['agency' => $tenant->name]),
                'highlights' => [
                    __('ui.drip_day13_h1'),
                    __('ui.drip_day13_h2'),
                ],
                'cta_label' => __('ui.drip_upgrade_cta'),
                'cta_url' => $baseUrl . '/agent/billing-and-invoices',
            ],
        };
    }
}
