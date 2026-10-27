@props(['status'])
@php
$s = is_object($status) ? $status->value : (string) $status;
$map = [
  'QUEUED' => ['zinc', 'Queued'], 'PENDING' => ['zinc', 'Pending'],
  'PROCESSING' => ['blue', 'Processing'], 'WAITING' => ['blue', 'Waiting'],
  'SENT' => ['indigo', 'Sent'], 'DELIVERED' => ['emerald', 'Delivered'], 'READ' => ['emerald', 'Read ✓✓'],
  'COMPLETED' => ['emerald', 'Completed'], 'PAID' => ['emerald', 'Lunas'], 'REFUNDED' => ['zinc', 'Dikembalikan'], 'sent' => ['emerald', 'Terkirim'], 'queued' => ['zinc', 'Antre'], 'skipped' => ['amber', 'Dilewati'], 'logged' => ['amber', 'Hanya log'], 'EXPIRED' => ['amber', 'Kedaluwarsa'], 'ACTIVE' => ['emerald', 'Active'], 'success' => ['emerald', 'Success'], 'active' => ['emerald', 'Active'],
  'PARTIAL' => ['amber', 'Partial'], 'SKIPPED' => ['amber', 'Skipped'], 'DRAFT' => ['zinc', 'Draft'], 'pending' => ['amber', 'Pending'],
  'FAILED' => ['red', 'Failed'], 'failed' => ['red', 'Failed'], 'CANCELED' => ['zinc', 'Canceled'], 'ARCHIVED' => ['zinc', 'Archived'], 'disabled' => ['red', 'Disabled'], 'degraded' => ['amber', 'Degraded'],
];
[$c, $label] = $map[$s] ?? ['zinc', $s];
$cls = [
  'zinc' => 'bg-zinc-500/15 text-zinc-300 border-zinc-500/30',
  'blue' => 'bg-info/15 text-blue-300 border-info/30',
  'indigo' => 'bg-cobalt/20 text-blue-300 border-cobalt/40',
  'emerald' => 'bg-ok/15 text-emerald-300 border-ok/30',
  'amber' => 'bg-warn/15 text-amber-300 border-warn/30',
  'red' => 'bg-err/15 text-red-300 border-err/30',
][$c];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded border px-2 py-0.5 text-[11px] font-mono font-bold whitespace-nowrap $cls"]) }}>{{ $label }}</span>
