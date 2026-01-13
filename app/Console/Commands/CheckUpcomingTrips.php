<?php

namespace App\Console\Commands;

use App\Models\Trip;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckUpcomingTrips extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-upcoming-trips';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for trips starting in 3 days and notify agents';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Define the target date: 3 days from now
        $targetDate = Carbon::now()->addDays(3)->format('Y-m-d');
        
        $trips = Trip::whereDate('start_date', $targetDate)
            ->whereIn('status', ['confirmed', 'active'])
            ->get();

        $this->info("Found " . $trips->count() . " trips starting on {$targetDate}");

        foreach ($trips as $trip) {
            // Find the agent associated with this trip's tenant
            // Assuming tenant_id in User model links to the tenant
            $agents = User::where('tenant_id', $trip->tenant_id)->get();

            foreach ($agents as $agent) {
                $customerName = $trip->customer ? $trip->customer->name : 'Unknown Customer';
                
                Notification::make()
                    ->title("Upcoming Trip: {$trip->destination}")
                    ->icon('heroicon-o-calendar')
                    ->body("Trip for {$customerName} starts in 3 days ({$trip->start_date->format('d/m/Y')}).")
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view')
                            ->label('View Trip')
                            ->url(\App\Filament\Agent\Resources\TripResource::getUrl('edit', ['record' => $trip->id]), shouldOpenInNewTab: true),
                    ])
                    ->sendToDatabase($agent);
                    
                $this->info("Notification sent to agent: {$agent->email} for trip ID: {$trip->id}");
            }
        }
        
        // Also check for trips starting tomorrow
        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        $tripsTomorrow = Trip::whereDate('start_date', $tomorrow)
            ->whereIn('status', ['confirmed', 'active'])
            ->get();
            
        foreach ($tripsTomorrow as $trip) {
             $agents = User::where('tenant_id', $trip->tenant_id)->get();

            foreach ($agents as $agent) {
                $customerName = $trip->customer ? $trip->customer->name : 'Unknown Customer';
                
                Notification::make()
                    ->title("Trip Starts Tomorrow!")
                    ->warning() // Yellow/Amber color for closer urgency
                    ->body("Trip to {$trip->destination} for {$customerName} starts tomorrow.")
                    ->actions([
                         \Filament\Notifications\Actions\Action::make('view')
                            ->label('View Trip')
                            ->url(\App\Filament\Agent\Resources\TripResource::getUrl('edit', ['record' => $trip->id]), shouldOpenInNewTab: true),
                    ])
                    ->sendToDatabase($agent);
            }
        }
        
        $this->info('Done.');
    }
}
