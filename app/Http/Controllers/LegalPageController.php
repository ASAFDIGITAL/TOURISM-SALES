<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LegalPageController extends Controller
{
    public function privacy(): View
    {
        return $this->renderPage('privacy');
    }

    public function accessibility(): View
    {
        return $this->renderPage('accessibility');
    }

    public function terms(): View
    {
        return $this->renderPage('terms');
    }

    protected function renderPage(string $type): View
    {
        $locale = in_array(app()->getLocale(), ['he', 'ar', 'en'], true) ? app()->getLocale() : 'he';
        $isRtl = in_array($locale, ['he', 'ar'], true);

        return view('legal.page', [
            'type' => $type,
            'locale' => $locale,
            'isRtl' => $isRtl,
            'contactPhoneDisplay' => '050-231-1554',
            'contactPhoneIntl' => '972502311554',
            'contactEmail' => 'tourism@asafdigital.com',
            'updatedDate' => now()->format('d/m/Y'),
        ]);
    }
}
