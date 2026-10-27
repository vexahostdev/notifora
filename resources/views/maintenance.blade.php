@extends('layouts.plain')
@section('title', 'Pemeliharaan')
@section('content')
<div class="card card-pad text-center mt-10">
  <h1 class="text-xl font-semibold text-white">Sedang dalam pemeliharaan</h1>
  <p class="text-sm text-zinc-400 mt-3">{{ $message }}</p>
  <p class="text-xs text-zinc-500 mt-4">Status layanan akan pulih otomatis. Pesan yang sudah masuk lewat API tidak hilang.</p>
</div>
@endsection
