{{ $p['title'] }}
{{ str_repeat('=', min(60, mb_strlen($p['title']))) }}

@foreach ($p['paragraphs'] ?? [] as $para)
{{ $para }}

@endforeach
@foreach ($p['details'] ?? [] as $label => $value)
{{ $label }}: {{ $value }}
@endforeach
@if (! empty($p['details']))

@endif
@if (! empty($p['cta']))
{{ $p['cta']['label'] }}: {{ $p['cta']['url'] }}

@endif
@if (! empty($p['note']))
{{ $p['note'] }}

@endif
--
Butuh bantuan? Balas email ini atau hubungi {{ $supportEmail }}
© {{ date('Y') }} notifora. All rights reserved. Created by notifora
