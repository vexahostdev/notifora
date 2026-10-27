@extends('layouts.plain')
@section('title', 'Akun ditangguhkan')
@section('content')
<div class="card card-pad text-center mt-10">
  <div class="mx-auto w-12 h-12 rounded-full bg-err/15 text-err flex items-center justify-center mb-4"><x-icon name="alert" class="w-6 h-6" /></div>
  <h1 class="text-xl font-semibold text-white">Workspace ditangguhkan</h1>
  <p class="text-sm text-zinc-400 mt-2">Akses ke <strong class="text-zinc-200">{{ $workspace->name }}</strong> dinonaktifkan sementara.@if ($workspace->suspended_reason) Alasan: {{ $workspace->suspended_reason }}.@endif</p>
  <p class="text-sm text-zinc-400 mt-3">API key tidak berfungsi selama penangguhan. Data Anda tetap aman. Hubungi <a class="text-link" href="mailto:{{ config('notifora.support_email') }}">{{ config('notifora.support_email') }}</a> untuk bantuan.</p>
</div>
@endsection
