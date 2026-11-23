<div style="display: flex; flex-direction: column; gap: 12px;">
    @if ($logs->isEmpty())
        <div style="padding: 24px; text-align: center; color: #64748b; font-size: 0.9rem;">
            {{ __('ui.no_rule_logs_yet') }}
        </div>
    @else
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead>
                    <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.3); text-align: start;">
                        <th style="padding: 8px 10px; text-align: start;">{{ __('ui.date') }}</th>
                        <th style="padding: 8px 10px; text-align: start;">{{ __('ui.trip') }} / {{ __('ui.customer') }}</th>
                        <th style="padding: 8px 10px; text-align: start;">{{ __('ui.channels_sent') }}</th>
                        <th style="padding: 8px 10px; text-align: start;">{{ __('ui.status') }}</th>
                        <th style="padding: 8px 10px; text-align: start;">{{ __('ui.email_tracking_column') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        @php
                            $status = $log->status ?: 'sent';
                            $badgeStyle = match ($status) {
                                'quota_exceeded' => 'background: rgba(239, 68, 68, 0.14); color: #ef4444;',
                                'partial' => 'background: rgba(245, 158, 11, 0.16); color: #d97706;',
                                default => 'background: rgba(16, 185, 129, 0.14); color: #10b981;',
                            };
                            $rem = $log->reminder;
                        @endphp
                        <tr style="border-bottom: 1px solid rgba(148, 163, 184, 0.16);">
                            <td style="padding: 8px 10px; white-space: nowrap;">
                                {{ $log->sent_at?->format('d/m/Y H:i') ?? '-' }}
                            </td>
                            <td style="padding: 8px 10px;">
                                <div style="font-weight: 700;">{{ $log->trip?->title ?? '#' . $log->trip_id }}</div>
                                @if ($log->trip?->customer?->name)
                                    <div style="font-size: 0.78rem; color: #64748b;">{{ $log->trip->customer->name }}</div>
                                @endif
                            </td>
                            <td style="padding: 8px 10px;">
                                {{ $log->channels_sent ? strtoupper(str_replace(',', ' + ', $log->channels_sent)) : '—' }}
                            </td>
                            <td style="padding: 8px 10px;">
                                <span style="display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.76rem; font-weight: 700; {{ $badgeStyle }}">
                                    {{ __('ui.rule_log_status_' . $status) }}
                                </span>
                                @if ($log->note)
                                    <div style="font-size: 0.74rem; color: #ef4444; margin-top: 2px;">
                                        {{ str_contains($log->note, 'email_quota_exceeded') ? __('ui.email_quota_reached_short') : '' }}
                                        {{ str_contains($log->note, 'sms_quota_exceeded') ? __('ui.sms_quota_reached_short') : '' }}
                                    </div>
                                @endif
                            </td>
                            <td style="padding: 8px 10px; white-space: nowrap;">
                                @if ($rem && $rem->channel === 'email')
                                    @if ($rem->opened_at)
                                        <span style="display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.76rem; font-weight: 700; background: rgba(16, 185, 129, 0.14); color: #10b981;">
                                            ✓✓ {{ __('ui.email_status_read_count', ['count' => max(1, (int) $rem->open_count)]) }}
                                        </span>
                                        <div style="font-size: 0.73rem; color: #64748b; margin-top: 2px;">
                                            {{ $rem->opened_at->format('d/m/Y H:i') }}
                                        </div>
                                    @elseif ($rem->status === 'failed')
                                        <span style="display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.76rem; font-weight: 700; background: rgba(239, 68, 68, 0.14); color: #ef4444;">
                                            ✗ {{ __('ui.email_status_failed') }}
                                        </span>
                                    @else
                                        <span style="display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 0.76rem; font-weight: 700; background: rgba(56, 189, 248, 0.14); color: #0284c7;">
                                            ✓ {{ __('ui.email_status_sent_unread') }}
                                        </span>
                                    @endif
                                @else
                                    <span style="color: #94a3b8;">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
