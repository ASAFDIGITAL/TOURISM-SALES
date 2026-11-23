<?php

return [
    'webhook_secret' => env('UPAY_WEBHOOK_SECRET', ''),
    'vat_rate' => (float) env('UPAY_VAT_RATE', env('PAYPLUS_VAT_RATE', 18)),
];
