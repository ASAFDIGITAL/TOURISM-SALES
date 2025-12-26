<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReceiptService
{
    public function generate($payments)
    {
        if ($payments instanceof \App\Models\Payment) {
            $payments = collect([$payments]);
        }

        return DB::transaction(function () use ($payments) {
            $firstPayment = $payments->first();
            $tenant = $firstPayment->tenant;
            $trip = $firstPayment->trip;
            
            // Generate Receipt Number
            $nextNumber = $tenant->receipt_next_number;
            $prefix = $tenant->receipt_prefix;
            $receiptNumber = $prefix ? $prefix . '-' . $nextNumber : $nextNumber;

            // Increment Tenant's sequence
            $tenant->increment('receipt_next_number');

            // Create Receipt Record
            $receipt = Receipt::create([
                'tenant_id' => $tenant->id,
                'trip_id' => $trip->id,
                'receipt_number' => $receiptNumber,
            ]);

            // Link all payments to this receipt
            foreach ($payments as $payment) {
                $payment->update(['receipt_id' => $receipt->id]);
            }

            return $receipt;
        });
    }

    public function downloadPdf(Receipt $receipt)
    {
        $trip = $receipt->trip;
        $customer = $trip->customer;
        $tenant = $receipt->tenant;

        $pdf = Pdf::loadView('pdf.receipt', compact('receipt', 'trip', 'customer', 'tenant'));
        
        return $pdf->download("{$receipt->receipt_number}.pdf");
    }
    
    public function getPdfContent(Receipt $receipt)
    {
        $trip = $receipt->trip;
        $customer = $trip->customer;
        $tenant = $receipt->tenant;

        return Pdf::loadView('pdf.receipt', compact('receipt', 'trip', 'customer', 'tenant'))
            ->setPaper('a4')
            ->output();
    }
}
