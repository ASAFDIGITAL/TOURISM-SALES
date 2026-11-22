@if (auth()->check() && auth()->user()?->getActiveTenantId())
    @livewire(\App\Livewire\AgentSupportWidget::class)
@endif
