@php
    $isRtl = in_array($localeCode ?? 'he', ['he', 'ar'], true);
    $dir = $isRtl ? 'rtl' : 'ltr';
    $align = $isRtl ? 'right' : 'left';
    $fontFamily = ($localeCode ?? 'he') === 'ar'
        ? "'Cairo', Tahoma, Arial, sans-serif"
        : "'Open Sans', Arial, sans-serif";
@endphp
<!DOCTYPE html>
<html lang="{{ $localeCode ?? 'he' }}" dir="{{ $dir }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0; padding:24px; background-color:#f1f5f9; font-family:{{ $fontFamily }}; direction:{{ $dir }}; text-align:{{ $align }}; color:#0f172a;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; margin:0 auto; background-color:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #e2e8f0;">
        <tr>
            <td style="background-color:#0f172a; padding:26px 28px; border-bottom:4px solid #f59e0b; text-align:{{ $align }};">
                <div style="font-size:12px; font-weight:700; color:#fbbf24; letter-spacing:0.06em; margin-bottom:6px;">
                    TOURISM-SALES CRM
                </div>
                <div style="font-size:21px; font-weight:800; color:#ffffff; line-height:1.35;">
                    {{ $heading }}
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding:26px 28px; font-size:15px; line-height:1.75; color:#1e293b; text-align:{{ $align }};">
                <p style="margin:0 0 16px;">{{ $introText }}</p>

                @if (! empty($highlights))
                    <table width="100%" cellpadding="0" cellspacing="0" style="margin:14px 0 22px; background-color:#f8fafc; border-radius:10px; border:1px solid #e2e8f0;">
                        <tr>
                            <td style="padding:16px 20px;">
                                @foreach ($highlights as $item)
                                    <div style="margin-bottom:8px; font-size:14px; color:#0f172a;">
                                        <span style="color:#d97706; font-weight:800;">✓</span>
                                        <span>{{ $item }}</span>
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    </table>
                @endif

                <div style="text-align:center; margin:24px 0 8px;">
                    <a
                        href="{{ $ctaUrl }}"
                        style="display:inline-block; padding:13px 28px; border-radius:10px; background-color:#f59e0b; color:#0f172a; font-weight:800; font-size:15px; text-decoration:none;"
                    >
                        {{ $ctaLabel }}
                    </a>
                </div>
            </td>
        </tr>
        <tr>
            <td style="background-color:#f8fafc; padding:16px 28px; border-top:1px solid #e2e8f0; font-size:12px; color:#64748b; text-align:{{ $align }};">
                <strong>TOURISM-SALES by ASAF DIGITAL</strong> &bull;
                WhatsApp: <a href="https://api.whatsapp.com/send?phone=972502311554" style="color:#d97706; text-decoration:none;">050-231-1554</a>
            </td>
        </tr>
    </table>
</body>
</html>
