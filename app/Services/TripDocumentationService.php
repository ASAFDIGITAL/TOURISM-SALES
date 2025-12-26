<?php

namespace App\Services;

use App\Models\Trip;
use Barryvdh\DomPDF\Facade\Pdf;

class TripDocumentationService
{
    public function getItineraryPdfContent(Trip $trip)
    {
        $customer = $trip->customer;
        $tenant = $trip->tenant;

        return Pdf::loadView('pdf.itinerary', compact('trip', 'customer', 'tenant'))
            ->setPaper('a4')
            ->output();
    }
}
