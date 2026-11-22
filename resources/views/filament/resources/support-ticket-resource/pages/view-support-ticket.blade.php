<x-filament-panels::page>
    <div
        wire:poll.5s="pollMessages"
        x-data="{
            scrollToBottom() {
                this.$nextTick(() => {
                    const el = this.$refs.adminChatMessages;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            }
        }"
        x-on:admin-support-chat-scroll.window="scrollToBottom()"
        class="ts-admin-chat-grid"
    >
        {{-- Main Conversation Thread --}}
        <div class="ts-admin-card ts-admin-chat-shell">
            {{-- Conversation Header --}}
            <div class="ts-admin-chat-header">
                <div style="min-width: 0;">
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span class="ts-support-ticket-subject" style="font-size: 1rem;">
                            {{ $this->record->tenant?->name }}
                        </span>
                        <span class="ts-support-badge ts-badge-waiting_agent">
                            {{ __('ui.ticket_category_' . $this->record->category) }}
                        </span>
                    </div>
                    <div class="ts-support-ticket-preview" style="margin-top: 3px;">
                        {{ __('ui.opened_by') }}: {{ $this->record->user?->name ?? '-' }} · {{ $this->record->created_at->format('d/m/Y H:i') }}
                    </div>
                </div>

                <span class="ts-support-badge ts-badge-{{ $this->record->status }}" style="padding: 4px 12px; font-size: 0.78rem;">
                    {{ __('ui.ticket_status_' . $this->record->status) }}
                </span>
            </div>

            {{-- Messages List --}}
            <div
                x-ref="adminChatMessages"
                x-init="scrollToBottom()"
                class="ts-admin-chat-messages"
            >
                @forelse ($this->record->messages as $msg)
                    @php
                        $isAdmin = $msg->isFromAdmin();
                    @endphp
                    <div class="ts-msg-row {{ $isAdmin ? 'ts-msg-row-start' : 'ts-msg-row-end' }}">
                        <div class="ts-msg-bubble {{ $isAdmin ? 'ts-msg-bubble-admin' : 'ts-msg-bubble-agent' }}" style="max-width: 78%; padding: 12px 15px;">
                            <div class="ts-msg-meta {{ $isAdmin ? 'ts-msg-meta-admin' : 'ts-msg-meta-agent' }}">
                                <span>
                                    {{ $isAdmin
                                        ? ($msg->user?->name ?? __('ui.support_team_prefix')) . ' (' . __('ui.admin_label') . ')'
                                        : ($msg->user?->name ?? $this->record->tenant?->name) }}
                                </span>
                                <span class="ts-msg-time">
                                    {{ $msg->created_at->format('d/m H:i') }}
                                </span>
                            </div>
                            <div>{{ $msg->body }}</div>
                        </div>
                    </div>
                @empty
                    <div style="padding: 48px 16px; text-align: center; font-size: 0.875rem; color: #94a3b8;">
                        {{ __('ui.no_messages_yet') }}
                    </div>
                @endforelse
            </div>

            {{-- Reply Box --}}
            <form wire:submit="sendReply" class="ts-admin-chat-composer">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <textarea
                        wire:model="replyBody"
                        rows="3"
                        placeholder="{{ __('ui.admin_reply_placeholder') }}"
                        x-on:keydown.meta.enter.prevent="$wire.sendReply()"
                        x-on:keydown.ctrl.enter.prevent="$wire.sendReply()"
                        class="ts-support-textarea"
                        style="resize: none;"
                        required
                    ></textarea>
                    @error('replyBody') <div class="ts-support-error">{{ $message }}</div> @enderror

                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
                        <span style="font-size: 0.76rem; color: #64748b;">
                            {{ __('ui.reply_notifies_agent_hint') }}
                        </span>
                        <x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled">
                            {{ __('ui.send_reply') }}
                        </x-filament::button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Sidebar: Ticket Controls & Agency Info --}}
        <div class="ts-admin-side-stack">
            {{-- Ticket Status & Priority --}}
            <div class="ts-admin-card">
                <div class="ts-admin-card-body">
                    <h3 class="ts-support-ticket-subject" style="margin: 0 0 14px 0; font-size: 0.92rem;">
                        {{ __('ui.ticket_details') }}
                    </h3>

                    <form wire:submit="updateTicketMeta" style="display: flex; flex-direction: column; gap: 12px;">
                        <div>
                            <label class="ts-support-label">{{ __('ui.status') }}</label>
                            <select wire:model="selectedStatus" class="ts-support-select">
                                @foreach (\App\Models\SupportTicket::statusOptions() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="ts-support-label">{{ __('ui.ticket_priority') }}</label>
                            <select wire:model="selectedPriority" class="ts-support-select">
                                @foreach (\App\Models\SupportTicket::priorityOptions() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <x-filament::button type="submit" color="gray" style="width: 100%;">
                            {{ __('ui.save_changes') }}
                        </x-filament::button>
                    </form>
                </div>
            </div>

            {{-- Agency Details --}}
            @if ($this->record->tenant)
                <div class="ts-admin-card">
                    <div class="ts-admin-card-body">
                        <h3 class="ts-support-ticket-subject" style="margin: 0 0 12px 0; font-size: 0.92rem;">
                            {{ __('ui.agency_info') }}
                        </h3>

                        <div>
                            <div class="ts-admin-info-row">
                                <span style="color: #64748b;">{{ __('ui.agent') }}</span>
                                <span class="ts-support-ticket-subject">{{ $this->record->tenant->name }}</span>
                            </div>
                            @if ($this->record->tenant->phone)
                                <div class="ts-admin-info-row">
                                    <span style="color: #64748b;">{{ __('ui.phone') }}</span>
                                    <span dir="ltr" style="font-weight: 600;">{{ $this->record->tenant->phone }}</span>
                                </div>
                            @endif
                            @if ($this->record->tenant->email)
                                <div class="ts-admin-info-row">
                                    <span style="color: #64748b;">{{ __('ui.email') }}</span>
                                    <span dir="ltr" style="font-weight: 600; overflow: hidden; text-overflow: ellipsis;">{{ $this->record->tenant->email }}</span>
                                </div>
                            @endif
                        </div>

                        <div style="margin-top: 14px;">
                            <form method="POST" action="{{ route('admin.impersonate.enter', $this->record->tenant) }}">
                                @csrf
                                <x-filament::button type="submit" color="warning" icon="heroicon-o-arrow-right-on-rectangle" style="width: 100%;">
                                    {{ __('ui.impersonate_agent') }}
                                </x-filament::button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
