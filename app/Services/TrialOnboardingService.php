<?php

namespace App\Services;

use App\Mail\TrialOnboardingMail;
use App\Models\Tenant;
use App\Models\TrialEmailTemplate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class TrialOnboardingService
{
    public const STEP_DAY_0 = 'day_0';
    public const STEP_DAY_7 = 'day_7';
    public const STEP_DAY_11 = 'day_11';
    public const STEP_DAY_13 = 'day_13';

    public function sendStepIfNeeded(Tenant $tenant, string $step, bool $force = false): bool
    {
        if (! $force && (! $tenant->is_trial || $tenant->status !== 'active')) {
            return false;
        }

        TrialEmailTemplate::ensureDefaultsExist();
        $template = TrialEmailTemplate::where('step', $step)->first();
        if (! $template || (! $force && ! $template->is_active)) {
            return false;
        }

        $sentSteps = is_array($tenant->trial_drip_steps_sent) ? $tenant->trial_drip_steps_sent : [];
        if (! $force && in_array($step, $sentSteps, true)) {
            return false;
        }

        $recipientEmail = $this->resolveOwnerEmail($tenant);
        if (! $recipientEmail) {
            return false;
        }

        $locale = in_array($tenant->language, ['he', 'ar', 'en'], true) ? $tenant->language : 'he';
        $payload = $template->renderForTenant($tenant, $locale);

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

        $template->forceFill([
            'sent_count' => (int) $template->sent_count + 1,
            'last_sent_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Manually send an editable Trial Drip template to selected tenants or a test email.
     *
     * @param  Collection<int, Tenant>  $tenants
     */
    public function sendTemplateManually(
        TrialEmailTemplate $template,
        Collection $tenants,
        ?string $testEmail = null,
        ?string $testLocale = null,
    ): int {
        $sent = 0;

        if (filled($testEmail) && filter_var(trim($testEmail), FILTER_VALIDATE_EMAIL)) {
            $locale = in_array($testLocale, ['he', 'ar', 'en'], true) ? $testLocale : 'he';
            $dummyTenant = $tenants->first() ?? new Tenant(['name' => 'Demo Agency', 'language' => $locale]);
            $payload = $template->renderForTenant($dummyTenant, $locale);

            Mail::to(trim($testEmail))->send(new TrialOnboardingMail(
                tenant: $dummyTenant,
                step: $template->step,
                subjectLine: $payload['subject'],
                heading: $payload['heading'],
                introText: $payload['intro'],
                highlights: $payload['highlights'],
                ctaLabel: $payload['cta_label'],
                ctaUrl: $payload['cta_url'],
                localeCode: $locale,
            ));

            $template->forceFill([
                'sent_count' => (int) $template->sent_count + 1,
                'last_sent_at' => now(),
            ])->save();

            return 1;
        }

        foreach ($tenants as $tenant) {
            try {
                if ($this->sendStepIfNeeded($tenant, $template->step, force: true)) {
                    $sent++;
                }
            } catch (\Throwable) {
            }
        }

        return $sent;
    }

    public function processAllTrialTenants(): int
    {
        TrialEmailTemplate::ensureDefaultsExist();

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
}
