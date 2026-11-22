@php
    $isRtl = in_array($localeCode ?? 'he', ['he', 'ar'], true);
    $dir = $isRtl ? 'rtl' : 'ltr';
    $align = $isRtl ? 'right' : 'left';
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
                <div style="font-size: 12px; font-weight: bold; color: #fbbf24; margin-bottom: 4px;">
                    {{ $tenant?->name ?? config('app.name') }}
                </div>
                <div style="font-size: 19px; font-weight: bold; color: #ffffff;">
                    {{ $subjectLine }}
                </div>
            </td>
        </tr>
        <tr>
            <td style="padding: 26px 28px; font-size: 14px; line-height: 1.75; color: #1e293b; text-align: {{ $align }};">
                {!! nl2br(preg_replace('/\*([^\*\n]+)\*/u', '<strong>$1</strong>', e($bodyText))) !!}
            </td>
        </tr>
        <tr>
            <td style="background-color: #f8fafc; padding: 14px 28px; border-top: 1px solid #e2e8f0; font-size: 12px; color: #64748b; text-align: {{ $align }};">
                <strong>{{ $tenant?->name }}</strong>
                @if($tenant?->email) &bull; {{ $tenant->email }} @endif
                @if($tenant?->phone) &bull; {{ $tenant->phone }} @endif
            </td>
        </tr>
    </table>
</body>
</html>
