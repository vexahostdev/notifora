<?php

return [
    // Operator platform wajib memakai 2FA (TOTP). Hanya boleh dimatikan di development lokal.
    'require_2fa' => (bool) env('ADMIN_2FA_REQUIRED', true),

    // URL health socket-engine (opsional), mis. http://notifora-socket:8042/health
    'socket_health_url' => env('SOCKET_HEALTH_URL'),

    // Feature flag / saklar platform yang tampil di Admin → Settings (key => label)
    // Akun operator awal (php artisan notifora:make-admin / db:seed). Isi hanya di .env asli, JANGAN di file *.example.
    'bootstrap' => [
        'name' => env('ADMIN_NAME', 'Platform Operator'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'flags' => [
        'signup_enabled' => 'Pendaftaran akun baru dibuka',
        'checkout_enabled' => 'Checkout & pembelian plan dibuka',
    ],

    // Jenis email sistem (kind => label) untuk Admin → Email → Template
    'email_kinds' => [
        'welcome' => 'Selamat datang',
        'password-reset' => 'Atur ulang password',
        'password-changed' => 'Password diubah',
        'invitation' => 'Undangan tim',
        'member-joined' => 'Anggota bergabung',
        'role-changed' => 'Peran diubah',
        'member-removed' => 'Akses dicabut',
        'apikey-created' => 'API key dibuat',
        'apikey-revoked' => 'API key dicabut',
        'quota-80' => 'Kuota 80%',
        'quota-100' => 'Kuota habis',
        'webhook-disabled' => 'Webhook dinonaktifkan',
        'provider-degraded' => 'Provider bermasalah',
        'invoice-created' => 'Invoice dibuat',
        'payment-success' => 'Pembayaran berhasil',
        'payment-failed' => 'Pembayaran gagal / kedaluwarsa',
        'subscription-expiring' => 'Plan akan berakhir',
        'subscription-expired' => 'Plan berakhir (masa tenggang)',
        'subscription-downgraded' => 'Turun ke Free',
        'workspace-suspended' => 'Workspace ditangguhkan',
        'workspace-reinstated' => 'Workspace diaktifkan kembali',
        'plan-granted' => 'Plan diberikan operator',
        'broadcast' => 'Broadcast operator',
        'smtp-test' => 'Tes SMTP',
    ],

    // Email keamanan/transaksional inti: tidak boleh dimatikan lewat template
    'email_locked' => ['password-reset', 'password-changed', 'invitation', 'apikey-created', 'workspace-suspended', 'payment-success'],

    // Alasan cepat saat suspend
    'suspend_reasons' => [
        'Pelanggaran kebijakan penggunaan (spam)',
        'Tunggakan / sengketa pembayaran',
        'Permintaan pemilik workspace',
        'Aktivitas mencurigakan (investigasi keamanan)',
    ],
];
