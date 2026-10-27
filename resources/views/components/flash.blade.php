@if (session('success') || session('error') || session('status'))
  <div class="mb-5 rounded-md border px-4 py-3 text-sm {{ session('error') ? 'border-err/40 bg-err/10 text-red-200' : 'border-ok/40 bg-ok/10 text-emerald-200' }}" role="status">
    {{ session('error') ?? session('success') ?? session('status') }}
  </div>
@endif
@if ($errors->any() && ! $errors->has('email') && ! request()->routeIs('login', 'register'))
  <div class="mb-5 rounded-md border border-err/40 bg-err/10 px-4 py-3 text-sm text-red-200" role="alert">
    <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  </div>
@endif
