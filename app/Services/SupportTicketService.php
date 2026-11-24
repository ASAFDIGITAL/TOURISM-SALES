<?php

namespace App\Services;

use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportTicketService
{
    public function __construct(
        protected WebPushService $webPush,
    ) {}

    public function createTicket(
        Tenant $tenant,
        ?User $creator,
        string $subject,
        string $body,
        string $category = SupportTicket::CATEGORY_GENERAL,
        string $priority = SupportTicket::PRIORITY_NORMAL,
        string $senderType = SupportMessage::SENDER_AGENT,
    ): SupportTicket {
        [$ticket, $message] = DB::transaction(function () use ($tenant, $creator, $subject, $body, $category, $priority, $senderType) {
            $now = now();
            $isFromAdmin = $senderType === SupportMessage::SENDER_ADMIN;

            $ticket = SupportTicket::create([
                'tenant_id' => $tenant->id,
                'user_id' => $creator?->id,
                'subject' => trim($subject),
                'category' => $category,
                'priority' => $priority,
                'status' => $isFromAdmin ? SupportTicket::STATUS_WAITING_AGENT : SupportTicket::STATUS_OPEN,
                'admin_unread_count' => $isFromAdmin ? 0 : 1,
                'agent_unread_count' => $isFromAdmin ? 1 : 0,
                'last_message_at' => $now,
            ]);

            $message = SupportMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $creator?->id,
                'sender_type' => $senderType,
                'body' => trim($body),
            ]);

            return [$ticket, $message];
        });

        $ticket->loadMissing(['tenant', 'user']);

        if ($senderType === SupportMessage::SENDER_AGENT) {
            $this->notifyAdmins($ticket, $message, isNewTicket: true);
        } else {
            $this->notifyAgentUsers($ticket, $message);
        }

        return $ticket;
    }

    public function addReply(
        SupportTicket $ticket,
        ?User $sender,
        string $body,
        string $senderType,
        ?string $nextStatus = null,
    ): SupportMessage {
        $message = DB::transaction(function () use ($ticket, $sender, $body, $senderType, $nextStatus) {
            $message = SupportMessage::create([
                'support_ticket_id' => $ticket->id,
                'user_id' => $sender?->id,
                'sender_type' => $senderType,
                'body' => trim($body),
            ]);

            $updates = [
                'last_message_at' => now(),
            ];

            if ($senderType === SupportMessage::SENDER_AGENT) {
                $updates['admin_unread_count'] = $ticket->admin_unread_count + 1;
                if ($ticket->status === SupportTicket::STATUS_CLOSED || $ticket->status === SupportTicket::STATUS_WAITING_AGENT) {
                    $updates['status'] = SupportTicket::STATUS_OPEN;
                    $updates['closed_at'] = null;
                }
            } else {
                $updates['agent_unread_count'] = $ticket->agent_unread_count + 1;
                $updates['status'] = $nextStatus ?: ($ticket->status === SupportTicket::STATUS_CLOSED ? SupportTicket::STATUS_WAITING_AGENT : SupportTicket::STATUS_WAITING_AGENT);
                if ($updates['status'] !== SupportTicket::STATUS_CLOSED) {
                    $updates['closed_at'] = null;
                }
            }

            $ticket->update($updates);

            return $message;
        });

        $ticket->refresh()->loadMissing(['tenant', 'user']);

        if ($senderType === SupportMessage::SENDER_AGENT) {
            $this->notifyAdmins($ticket, $message, isNewTicket: false);
        } else {
            $this->notifyAgentUsers($ticket, $message);
        }

        return $message;
    }

    public function markReadForAdmin(SupportTicket $ticket): void
    {
        if ($ticket->admin_unread_count > 0) {
            $ticket->update(['admin_unread_count' => 0]);
        }

        $ticket->messages()
            ->where('sender_type', SupportMessage::SENDER_AGENT)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markReadForAgent(SupportTicket $ticket): void
    {
        if ($ticket->agent_unread_count > 0) {
            $ticket->update(['agent_unread_count' => 0]);
        }

        $ticket->messages()
            ->where('sender_type', SupportMessage::SENDER_ADMIN)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function updateStatus(SupportTicket $ticket, string $status): void
    {
        $ticket->update([
            'status' => $status,
            'closed_at' => $status === SupportTicket::STATUS_CLOSED ? now() : null,
        ]);
    }

    protected function notifyAdmins(SupportTicket $ticket, SupportMessage $message, bool $isNewTicket): void
    {
        $admins = User::query()->where('role', 'super_admin')->get();

        if ($admins->isEmpty()) {
            return;
        }

        $tenantName = $ticket->tenant?->name ?? '#' . $ticket->tenant_id;
        $snippet = Str::limit($message->body, 110);

        $title = $isNewTicket
            ? __('ui.admin_new_ticket_title', ['tenant' => $tenantName])
            : __('ui.admin_ticket_reply_title', ['tenant' => $tenantName]);

        $body = $ticket->subject . ' — ' . $snippet;

        $ticketUrl = null;
        try {
            $ticketUrl = SupportTicketResource::getUrl('view', ['record' => $ticket], panel: 'admin');
        } catch (\Throwable) {
            $ticketUrl = '/admin/support-tickets/' . $ticket->id;
        }

        foreach ($admins as $admin) {
            $notification = Notification::make()
                ->title($title)
                ->body($body)
                ->icon('heroicon-o-chat-bubble-left-right')
                ->warning();

            if ($ticketUrl) {
                $notification->actions([
                    NotificationAction::make('open_ticket')
                        ->label(__('ui.open_conversation'))
                        ->url($ticketUrl)
                        ->button(),
                ]);
            }

            $notification->sendToDatabase($admin);

            try {
                $this->webPush->sendToUser(
                    $admin,
                    $title,
                    $body,
                    url: $ticketUrl,
                    tag: 'support-ticket-' . $ticket->id,
                );
            } catch (\Throwable) {
                // Keep ticket creation/reply resilient even if push delivery fails
            }

            app(AdminAlertMailService::class)->sendToAdmin(
                admin: $admin,
                subject: $title,
                body: $message->body,
                badge: __('ui.admin_alert_badge_support'),
                details: [
                    __('ui.agency_name') => $tenantName,
                    __('ui.subject') => $ticket->subject,
                    __('ui.phone') => $ticket->tenant?->phone ?? '',
                    __('ui.email') => $ticket->tenant?->email ?? '',
                ],
                actionLabel: __('ui.open_conversation'),
                actionUrl: $ticketUrl,
                tone: 'warning',
            );
        }
    }

    protected function notifyAgentUsers(SupportTicket $ticket, SupportMessage $message): void
    {
        $agents = User::query()
            ->where('tenant_id', $ticket->tenant_id)
            ->where('role', 'agent')
            ->get();

        if ($agents->isEmpty()) {
            return;
        }

        $title = __('ui.agent_support_reply_title', ['subject' => Str::limit($ticket->subject, 60)]);
        $body = Str::limit($message->body, 120);

        foreach ($agents as $agent) {
            Notification::make()
                ->title($title)
                ->body($body)
                ->icon('heroicon-o-chat-bubble-left-right')
                ->info()
                ->sendToDatabase($agent);

            try {
                $this->webPush->sendToUser(
                    $agent,
                    $title,
                    $body,
                    url: '/agent',
                    tag: 'support-reply-' . $ticket->id,
                );
            } catch (\Throwable) {
                // Keep reply flow resilient
            }
        }
    }
}
