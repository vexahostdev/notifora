@php
  $tones = [
    'info' => ['#2244A8', '#EEF2FB'],
    'success' => ['#047857', '#ECFDF5'],
    'warn' => ['#B45309', '#FFFBEB'],
    'danger' => ['#B91C1C', '#FEF2F2'],
  ];
  [$accent, $soft] = $tones[$p['tone']] ?? $tones['info'];
  $logo = \App\Services\Brand::logoUrl();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light only">
  <title>{{ $p['title'] }}</title>
</head>
<body style="margin:0;padding:0;background:#E9ECF2;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#111827;">
  @if (! empty($p['preheader']))
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $p['preheader'] }}</div>
  @endif
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#E9ECF2;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
        <tr><td style="padding:0 4px 14px 4px;">
          <table role="presentation" cellpadding="0" cellspacing="0"><tr>
            <td style="vertical-align:middle;"><img src="{{ $logo }}" width="30" height="30" alt="" style="display:block;border:0;"></td>
            <td style="vertical-align:middle;padding-left:10px;font-weight:800;letter-spacing:.08em;font-size:14px;color:#0D0D0D;text-transform:uppercase;">{{ \App\Services\Brand::name() }}</td>
          </tr></table>
        </td></tr>
        <tr><td style="background:#FFFFFF;border:2px solid #0D0D0D;box-shadow:6px 6px 0 {{ $accent }};padding:28px 26px;">
          <div style="height:4px;width:44px;background:{{ $accent }};margin-bottom:18px;"></div>
          <h1 style="margin:0 0 14px 0;font-size:20px;line-height:1.3;color:#0D0D0D;">{{ $p['title'] }}</h1>

          @foreach ($p['paragraphs'] ?? [] as $para)
            <p style="margin:0 0 14px 0;font-size:14.5px;line-height:1.65;color:#374151;">{{ $para }}</p>
          @endforeach

          @if (! empty($p['details']))
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 18px 0;background:{{ $soft }};border:1px solid #E5E7EB;">
              @foreach ($p['details'] as $label => $value)
                <tr>
                  <td style="padding:9px 14px;font-size:12px;color:#6B7280;width:38%;border-bottom:1px solid #E5E7EB;">{{ $label }}</td>
                  <td style="padding:9px 14px;font-size:13px;font-weight:600;color:#111827;border-bottom:1px solid #E5E7EB;word-break:break-word;">{{ $value }}</td>
                </tr>
              @endforeach
            </table>
          @endif

          @if (! empty($p['cta']))
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 18px 0;"><tr><td style="background:{{ $accent }};">
              <a href="{{ $p['cta']['url'] }}" style="display:inline-block;padding:12px 22px;font-size:13px;font-weight:700;letter-spacing:.04em;color:#FFFFFF;text-decoration:none;text-transform:uppercase;">{{ $p['cta']['label'] }}</a>
            </td></tr></table>
            <p style="margin:0 0 14px 0;font-size:12px;color:#6B7280;line-height:1.5;">Jika tombol tidak berfungsi, salin tautan ini ke browser:<br><a href="{{ $p['cta']['url'] }}" style="color:{{ $accent }};word-break:break-all;">{{ $p['cta']['url'] }}</a></p>
          @endif

          @if (! empty($p['note']))
            <p style="margin:6px 0 0 0;padding-top:14px;border-top:1px solid #E5E7EB;font-size:12.5px;line-height:1.6;color:#6B7280;">{{ $p['note'] }}</p>
          @endif
        </td></tr>
        <tr><td style="padding:16px 6px 0 6px;font-size:11.5px;line-height:1.6;color:#6B7280;text-align:center;">
          Butuh bantuan? Balas email ini atau hubungi <a href="mailto:{{ $supportEmail }}" style="color:#2244A8;">{{ $supportEmail }}</a><br>
          © {{ date('Y') }} notifora. All rights reserved. Created by notifora
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
