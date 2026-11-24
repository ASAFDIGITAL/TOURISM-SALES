@php
    $isRtl = in_array($lang, ['he', 'ar'], true);
    $dir = $isRtl ? 'rtl' : 'ltr';
    $align = $isRtl ? 'right' : 'left';

    $badgeColors = match ($tone) {
        'success' => ['bg' => '#dcfce7', 'text' => '#166534'],
        'warning' => ['bg' => '#fef3c7', 'text' => '#92400e'],
        'danger'  => ['bg' => '#fee2e2', 'text' => '#991b1b'],
        default   => ['bg' => '#e0f2fe', 'text' => '#075985'],
    };
@endphp
<!doctype html>
<html lang="{{ $lang }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $alertSubject }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;direction:{{ $dir }};">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background-color:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;">
                {{-- Navy Brand Header --}}
                <tr>
                    <td style="background-color:#0f172a;padding:22px 28px;border-bottom:4px solid #f59e0b;text-align:{{ $align }};">
                        <div style="font-size:18px;font-weight:bold;color:#ffffff;letter-spacing:0.4px;" dir="ltr">
                            TOURISM-SALES <span style="color:#f59e0b;font-size:12px;font-weight:normal;">· Admin Alert</span>
                        </div>
                    </td>
                </tr>

                {{-- Main Body --}}
                <tr>
                    <td style="padding:26px 28px;text-align:{{ $align }};">
                        @if(filled($badgeText))
                            <div style="margin-bottom:12px;">
                                <span style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:bold;background-color:{{ $badgeColors['bg'] }};color:{{ $badgeColors['text'] }};">
                                    {{ $badgeText }}
                                </span>
                            </div>
                        @endif

                        <h1 style="margin:0 0 12px 0;font-size:20px;line-height:1.35;color:#0f172a;">
                            {{ $alertSubject }}
                        </h1>

                        <p style="margin:0 0 18px 0;font-size:14px;line-height:1.65;color:#334155;white-space:pre-line;">{{ $alertBody }}</p>

                        @if(count($details) > 0)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px 0;background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;border-collapse:separate;">
                                @foreach($details as $label => $value)
                                    <tr>
                                        <td style="padding:9px 14px;font-size:13px;color:#64748b;font-weight:bold;border-bottom:1px solid #e2e8f0;width:36%;text-align:{{ $align }};">
                                            {{ $label }}
                                        </td>
                                        <td style="padding:9px 14px;font-size:13px;color:#0f172a;font-weight:600;border-bottom:1px solid #e2e8f0;text-align:{{ $align }};">
                                            {{ $value }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        @endif

                        @if(filled($actionUrl) && filled($actionLabel))
                            <div style="margin-top:8px;text-align:{{ $align }};">
                                <a href="{{ $actionUrl }}"
                                   style="display:inline-block;background-color:#f59e0b;color:#0f172a;text-decoration:none;font-weight:bold;font-size:14px;padding:12px 24px;border-radius:10px;">
                                    {{ $actionLabel }}
                                </a>
                            </div>
                        @endif
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="background-color:#f8fafc;padding:14px 28px;border-top:1px solid #e2e8f0;font-size:11px;color:#64748b;text-align:{{ $align }};">
                        TOURISM-SALES · ASAF DIGITAL &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
