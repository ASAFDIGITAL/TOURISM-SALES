<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Resources\SupportTicketResource;
use App\Filament\Resources\TenantResource;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected static string $view = 'filament.resources.support-ticket-resource.pages.view-support-ticket';

    public string $replyBody = '';

    public string $selectedStatus = '';

    public string $selectedPriority = '';

    public function mount(int | string $record): void
    {
        parent::mount($record);

        /** @var SupportTicket $ticket */
        $ticket = $this->record;
        $this->selectedStatus = $ticket->status;
        $this->selectedPriority = $ticket->priority;

        app(SupportTicketService::class)->markReadForAdmin($ticket);
        $this->record->refresh()->load(['tenant', 'user', 'messages.user']);
    }

    public function getTitle(): string
    {
        /** @var SupportTicket $ticket */
        $ticket = $this->record;

        return '#' . $ticket->id . ' · ' . $ticket->subject;
    }

    protected function getHeaderActions(): array
    {
        /** @var SupportTicket $ticket */
        $ticket = $this->record;

        return [
            Actions\Action::make('edit_tenant')
                ->label(__('ui.manage_agent'))
                ->icon('heroicon-o-building-office-2')
                ->color('gray')
                ->url(fn (): string => TenantResource::getUrl('edit', ['record' => $ticket->tenant_id]))
                ->visible(fn (): bool => $ticket->tenant_id !== null),

            Actions\Action::make('toggle_close')
                ->label(fn (): string => $this->record->isClosed() ? __('ui.reopen_ticket') : __('ui.close_ticket'))
                ->icon(fn (): string => $this->record->isClosed() ? 'heroicon-o-arrow-path' : 'heroicon-o-check-circle')
                ->color(fn (): string => $this->record->isClosed() ? 'warning' : 'success')
                ->action(function (): void {
                    $next = $this->record->isClosed()
                        ? SupportTicket::STATUS_OPEN
                        : SupportTicket::STATUS_CLOSED;

                    app(SupportTicketService::class)->updateStatus($this->record, $next);
                    $this->selectedStatus = $next;
                    $this->record->refresh();

                    Notification::make()
                        ->title(__('ui.ticket_status_updated'))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function sendReply(): void
    {
        $this->validate([
            'replyBody' => ['required', 'string', 'min:1', 'max:4000'],
        ]);

        /** @var SupportTicket $ticket */
        $ticket = $this->record;

        app(SupportTicketService::class)->addReply(
            ticket: $ticket,
            sender: Auth::user(),
            body: $this->replyBody,
            senderType: SupportMessage::SENDER_ADMIN,
        );

        $this->replyBody = '';
        $this->record->refresh()->load(['tenant', 'user', 'messages.user']);
        $this->selectedStatus = $this->record->status;

        $this->dispatch('admin-support-chat-scroll');
    }

    public function updateTicketMeta(): void
    {
        /** @var SupportTicket $ticket */
        $ticket = $this->record;

        $this->validate([
            'selectedStatus' => ['required', 'in:open,in_progress,waiting_agent,closed'],
            'selectedPriority' => ['required', 'in:low,normal,high,urgent'],
        ]);

        app(SupportTicketService::class)->updateStatus($ticket, $this->selectedStatus);
        $ticket->update(['priority' => $this->selectedPriority]);
        $this->record->refresh();

        Notification::make()
            ->title(__('ui.ticket_status_updated'))
            ->success()
            ->send();
    }

    public function pollMessages(): void
    {
        /** @var SupportTicket $ticket */
        $ticket = $this->record;

        app(SupportTicketService::class)->markReadForAdmin($ticket);
        $this->record->refresh()->load(['tenant', 'user', 'messages.user']);
        $this->selectedStatus = $this->record->status;
        $this->selectedPriority = $this->record->priority;
    }
}
