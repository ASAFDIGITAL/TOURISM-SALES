@php
    $isRtl = in_array($localeCode ?? 'he', ['he', 'ar'], true);
    $dir = $isRtl ? 'rtl' : 'ltr';
    $align = $isRtl ? 'right' : 'left';
    $ctaLabel = match ($localeCode ?? 'he') {
        'ar' => 'الدفع وتجديد الاشتراك الآن',
        'en' => 'Pay & Renew Subscription Now',
        default => 'לתשלום וחידוש המנוי עכשיו',
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $localeCode ?? 'he' }}" dir="{{ $dir }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin: 0; padding: 24px; background-color: #f1f5f9; font-family: 'Open Sans', Arial, sans-serif; direction: {{ $dir }}; text-align: {{ $align }}; color: #1e293b;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0;">
        <tr>
            <td style="background-color: #0f172a; padding: 22px 28px; border-bottom: 4px solid #F59E0B; text-align: {{ $align }};">
                <div style="font-size: 12px; font-weight: bold; color: #F59E0B; margin-bottom: 4px;">
                    TOURISM-SALES by ASAF DIGITAL
                </div>
                <div style="font-size: 19px; font-weight: bold; color: #ffffff;">
                    {{ $subjectLine }}
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding: 26px 28px; font-size: 14px; line-height: 1.75; color: #1e293b; text-align: {{ $align }};">
                {!! nl2br(preg_replace('/\*([^\*\n]+)\*/u', '<strong>$1</strong>', e($bodyText))) !!}

                @if(!empty($paymentUrl))
                    <div style="margin-top: 24px; text-align: center;">
                        <a
                            href="{{ $paymentUrl }}"
                            target="_blank"
                            style="
                                display: inline-block;
                                background-color: #F59E0B;
                                color: #ffffff;
                                text-decoration: none;
                                font-weight: bold;
                                font-size: 15px;
                                padding: 12px 28px;
                                border-radius: 8px;
                            "
                        >
                            {{ $ctaLabel }}
                        </a>
                    </div>
                @endif
            </td>
        </tr>
        <tr>
            <td style="background-color: #f8fafc; padding: 14px 28px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: {{ $align }};">
                TOURISM-SALES by ASAF DIGITAL
            </td>
        </tr>
    </table>
</body>
</html>
