@extends('layouts.app')
@section('title', 'Checkout '.$plan['label'])
@section('content')
<x-page-header :title="'Checkout — '.$plan['label']" subtitle="Pilih durasi dan metode pembayaran. Anda akan diarahkan ke halaman pembayaran Xendit.">
  <a href="{{ route('console.billing.index') }}" class="btn-secondary">← Billing</a>
</x-page-header>

@if ($blocked)<div class="mb-5 rounded-md border border-err/40 bg-err/10 px-4 py-3 text-sm text-red-200" role="alert">{{ $blocked }}</div>@endif
@error('checkout')<div class="mb-5 rounded-md border border-err/40 bg-err/10 px-4 py-3 text-sm text-red-200" role="alert">{{ $message }}</div>@enderror
@if (! $configured)
  <div class="mb-5 rounded-md border border-warn/40 bg-warn/10 px-4 py-3 text-sm text-amber-200">Pembayaran online belum dikonfigurasi di environment ini. Hubungi <a class="underline" href="mailto:{{ config('notifora.support_email') }}">{{ config('notifora.support_email') }}</a>.</div>
@endif

<form method="POST" action="{{ route('console.billing.store') }}" class="grid lg:grid-cols-3 gap-5"
      @submit="if (checking) { $event.preventDefault(); couponMsg = 'Tunggu pemeriksaan kupon selesai.'; } else if (submitting) { $event.preventDefault(); } else { submitting = true; }"
      x-data="{ months: {{ (int) old('months', $months) }}, price: {{ $plan['price'] }}, base: {{ $baseTs }}, method: @js(old('method', 'all')),
                coupon: @js(old('coupon', '')), discount: 0, couponMsg: '', couponOk: false, checking: false, submitting: false, couponRequest: 0,
                invalidateCoupon() {
                  this.couponRequest++;
                  this.discount = 0; this.couponOk = false; this.couponMsg = ''; this.checking = false;
                },
                async applyCoupon() {
                  const coupon = this.coupon.trim();
                  const months = this.months;
                  const request = ++this.couponRequest;
                  this.couponMsg = ''; this.discount = 0; this.couponOk = false;
                  if (!coupon) { this.checking = false; return; }
                  this.checking = true;
                  try {
                    const r = await fetch('{{ route('console.billing.coupon-preview') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ plan: '{{ $planKey }}', months, coupon }) });
                    const j = await r.json().catch(() => ({}));
                    if (request !== this.couponRequest) return;
                    if (r.ok) { this.discount = j.discount; this.couponOk = true; this.couponMsg = 'Kupon diterapkan: hemat ' + this.rp(j.discount); }
                    else { this.couponMsg = j.message || (j.errors ? Object.values(j.errors)[0][0] : 'Kupon tidak valid.'); }
                  } catch (e) { if (request === this.couponRequest) this.couponMsg = 'Gagal memeriksa kupon.'; }
                  finally { if (request === this.couponRequest) this.checking = false; }
                },
                get total() { return Math.max(0, this.price * this.months - this.discount) },
                get until() { const d = new Date(this.base * 1000); d.setMonth(d.getMonth() + this.months);
                  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) },
                rp(n) { return 'Rp ' + n.toLocaleString('id-ID') } }">
  @csrf
  <input type="hidden" name="plan" value="{{ $planKey }}">

  <div class="lg:col-span-2 space-y-5">
    <fieldset class="card card-pad">
      <legend class="font-semibold text-white mb-3">1. Durasi langganan</legend>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @foreach ($periods as $m)
          <label class="cursor-pointer">
            <input type="radio" name="months" value="{{ $m }}" class="peer sr-only" x-model.number="months" @change="invalidateCoupon(); applyCoupon()" @checked((int) old('months', $months) === $m)>
            <span class="block rounded-md border border-line p-3 text-center peer-checked:border-cobalt peer-checked:bg-cobalt/10 peer-focus-visible:ring-2 ring-cobalt">
              <span class="block font-semibold text-white">{{ $m }} bulan</span>
              <span class="block text-xs font-mono text-zinc-400 mt-1">Rp {{ number_format($plan['price'] * $m, 0, ',', '.') }}</span>
            </span>
          </label>
        @endforeach
      </div>
      @error('months')<p class="error-text">{{ $message }}</p>@enderror
    </fieldset>

    <fieldset class="card card-pad">
      <legend class="font-semibold text-white mb-3">2. Metode pembayaran</legend>
      <div class="grid sm:grid-cols-2 gap-2">
        @foreach ($methods as $key => $m)
          <label class="cursor-pointer">
            <input type="radio" name="method" value="{{ $key }}" class="peer sr-only" x-model="method" @checked(old('method', 'all') === $key)>
            <span class="block rounded-md border border-line px-3 py-2.5 text-sm peer-checked:border-cobalt peer-checked:bg-cobalt/10 peer-focus-visible:ring-2 ring-cobalt">{{ $m['label'] }}</span>
          </label>
        @endforeach
      </div>
      <p class="hint">Metode lain tetap bisa dipilih di halaman Xendit bila Anda memilih “Semua metode”.</p>
      @error('method')<p class="error-text">{{ $message }}</p>@enderror
    </fieldset>

    <fieldset class="card card-pad">
      <legend class="font-semibold text-white mb-3">3. Kode kupon (opsional)</legend>
      <div class="flex flex-col sm:flex-row gap-2">
        <input name="coupon" x-model="coupon" @input="invalidateCoupon()" @keydown.enter.prevent="applyCoupon()" maxlength="40" class="input input-mono uppercase sm:max-w-xs" placeholder="KODEKUPON" aria-label="Kode kupon" autocomplete="off">
        <button type="button" class="btn-secondary w-full sm:w-auto" @click="applyCoupon()" :disabled="checking"><span x-text="checking ? 'Memeriksa…' : 'Terapkan'">Terapkan</span></button>
      </div>
      <p class="text-sm mt-2" :class="couponOk ? 'text-ok' : 'text-err'" x-show="couponMsg" x-text="couponMsg" role="status"></p>
    </fieldset>

    <label class="flex items-start gap-2 text-sm text-zinc-300">
      <input type="checkbox" name="terms" value="1" required class="mt-1 accent-[#0033FF]" @checked(old('terms'))>
      <span>Saya memahami bahwa plan aktif otomatis setelah pembayaran terkonfirmasi, invoice berlaku 24 jam, dan pembayaran yang sudah berhasil tidak dapat dibatalkan dari halaman ini (hubungi support untuk bantuan).</span>
    </label>
    @error('terms')<p class="error-text">{{ $message }}</p>@enderror
  </div>

  <aside class="lg:col-span-1">
    <div class="card card-pad lg:sticky lg:top-20 space-y-3">
      <h2 class="font-semibold text-white">Ringkasan pesanan</h2>
      <dl class="text-sm space-y-2">
        <div class="flex justify-between"><dt class="text-zinc-400">Workspace</dt><dd class="text-right text-white truncate max-w-40">{{ $ws->name }}</dd></div>
        <div class="flex justify-between"><dt class="text-zinc-400">Plan</dt><dd class="text-white">{{ $plan['label'] }}</dd></div>
        <div class="flex justify-between"><dt class="text-zinc-400">Durasi</dt><dd class="text-white" x-text="months + ' bulan'"></dd></div>
        <div class="flex justify-between"><dt class="text-zinc-400">Harga / bulan</dt><dd class="font-mono text-white" x-text="rp(price)"></dd></div>
        <div class="flex justify-between" x-show="discount > 0" x-cloak><dt class="text-zinc-400">Diskon kupon</dt><dd class="font-mono text-ok" x-text="'− ' + rp(discount)"></dd></div>
        <div class="flex justify-between"><dt class="text-zinc-400">{{ $renewing ? 'Berlaku sampai (setelah perpanjang)' : 'Aktif sampai' }}</dt><dd class="text-right text-white" x-text="until"></dd></div>
      </dl>
      <div class="border-t border-line pt-3 flex items-end justify-between"><span class="text-zinc-400 text-sm">Total</span><span class="font-mono text-2xl font-semibold text-white" x-text="rp(total)">Rp {{ number_format($plan['price'] * $months, 0, ',', '.') }}</span></div>
      <button class="btn-primary w-full" :disabled="{{ $blocked || ! $configured ? 'true' : 'false' }} || checking || submitting"><x-icon name="card" /><span x-text="submitting ? 'Memproses…' : (total === 0 ? 'Aktifkan gratis' : 'Bayar sekarang')">Bayar sekarang</span></button>
      <p class="hint text-center">Dialihkan ke Xendit (QRIS, VA, Indomaret). Harga dalam Rupiah.</p>
    </div>
  </aside>
</form>
@endsection
