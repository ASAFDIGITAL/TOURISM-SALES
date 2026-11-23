<?php

namespace App\Filament\Agent\Widgets;

use App\Models\ReminderRuleLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MessagingQuotaWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $tenant = auth()->user()?->getActiveTenant()?->load('plan');

        if (! $tenant) {
            return [];
        }

        $emailSent = $tenant->getMonthlyEmailSentCount();
        $emailLimit = $tenant->getMonthlyEmailLimit();
        $emailRemaining = $tenant->getRemainingMonthlyEmails();
        $emailColor = $this->resolveUsageColor($emailSent, $emailLimit);

        $smsSent = $tenant->getMonthlySmsSentCount();
        $smsLimit = $tenant->getMonthlySmsLimit();
        $smsRemaining = $tenant->getRemainingMonthlySms();
        $smsColor = $this->resolveUsageColor($smsSent, $smsLimit);

        $ruleLogsMonthly = ReminderRuleLog::query()
            ->whereHas('rule', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->whereBetween('sent_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        $ruleLogsTotal = ReminderRuleLog::query()
            ->whereHas('rule', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->count();

        return [
            Stat::make(
                __('ui.monthly_emails_usage'),
                $emailLimit !== null ? "{$emailSent} / {$emailLimit}" : "{$emailSent} / ∞"
            )
                ->description(
                    $emailLimit !== null
                        ? __('ui.quota_remaining_label', ['count' => $emailRemaining])
                        : __('ui.unlimited_quota')
                )
                ->descriptionIcon('heroicon-m-envelope')
                ->color($emailColor),

            Stat::make(
                __('ui.monthly_sms_usage'),
                $smsLimit !== null ? "{$smsSent} / {$smsLimit}" : "{$smsSent} / ∞"
            )
                ->description(
                    $smsLimit !== null
                        ? __('ui.quota_remaining_label', ['count' => $smsRemaining])
                        : __('ui.unlimited_quota')
                )
                ->descriptionIcon('heroicon-m-chat-bubble-left-ellipsis')
                ->color($smsColor),

            Stat::make(
                __('ui.auto_reminders_sent_this_month'),
                (string) $ruleLogsMonthly
            )
                ->description(__('ui.total_all_time_sent', ['count' => $ruleLogsTotal]))
                ->descriptionIcon('heroicon-m-bolt')
                ->color('primary'),
        ];
    }

    private function resolveUsageColor(int $sent, ?int $limit): string
    {
        if ($limit === null || $limit <= 0) {
            return 'success';
        }

        $ratio = $sent / $limit;

        if ($ratio >= 1.0) {
            return 'danger';
        }

        if ($ratio >= 0.8) {
            return 'warning';
        }

        return 'success';
    }
}
