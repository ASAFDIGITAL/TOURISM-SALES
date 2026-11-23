<?php

return [
    'enabled' => (bool) env('PAYPLUS_ENABLED', false),

    // 'sandbox' (restapidev.payplus.co.il) or 'production' (restapi.payplus.co.il)
    'env' => env('PAYPLUS_ENV', 'sandbox'),

    'api_key' => env('PAYPLUS_API_KEY'),
    'secret_key' => env('PAYPLUS_SECRET_KEY'),
    'payment_page_uid' => env('PAYPLUS_PAYMENT_PAGE_UID'),
    'terminal_uid' => env('PAYPLUS_TERMINAL_UID'),

    // Israeli VAT percentage added to net (+VAT) plan prices
    'vat_rate' => (float) env('PAYPLUS_VAT_RATE', 18),

    // Automatically issue PayPlus Invoice+ digital tax invoice/receipt on initial & recurring charges
    'issue_invoice' => (bool) env('PAYPLUS_ISSUE_INVOICE', true),
];
