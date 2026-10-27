<?php

return [
    'api_url' => env('API_URL', 'https://api.notifora.my.id'),
    'support_email' => env('MAIL_SUPPORT_ADDRESS', 'support@notifora.my.id'),

    // Kuota & batas per plan (PRD §8.4 dan §12.2)
    'plans' => [
        'starter' => ['label' => 'Starter', 'events' => 10000, 'rpm' => 600, 'retention_days' => 7, 'price' => 99000],
        'pro' => ['label' => 'Pro', 'events' => 50000, 'rpm' => 3000, 'retention_days' => 30, 'price' => 299000],
        'business' => ['label' => 'Business', 'events' => 250000, 'rpm' => 12000, 'retention_days' => 90, 'price' => 799000],
        'scale' => ['label' => 'Scale', 'events' => 1000000, 'rpm' => 60000, 'retention_days' => 90, 'price' => 1999000],
    ],
    // Plan gratis untuk workspace baru / setelah masa berlaku & tenggang habis
    'free_plan' => [
        'label' => 'Free', 'events' => 1000, 'rpm' => 120, 'retention_days' => 3, 'price' => 0,
    ],
    'dev_events_monthly' => 1000,
    'quota_grace' => 0.10,

    // Penagihan (Xendit). Plan berbayar berlaku per periode bulan; ada masa tenggang sebelum turun ke Free.
    'billing' => [
        'periods' => [1, 3, 6, 12],
        'grace_days' => (int) env('BILLING_GRACE_DAYS', 3),
        'reminder_days' => [7, 3, 1],
        'invoice_ttl' => 86400,          // detik; invoice Xendit kedaluwarsa setelah 24 jam
        'external_prefix' => 'NTF-',     // prefix routing di router webhook pusat VexaHost
    ],

    'limits' => [
        'max_steps' => 20,
        'max_delay_seconds' => 30 * 86400,
        'max_payload_bytes' => 64 * 1024,
        'bulk_max' => 100,
    ],

    'socket' => [
        'public_url' => env('SOCKET_PUBLIC_URL', 'wss://ws.notifora.my.id'),
        'jwt_secret' => env('SOCKET_JWT_SECRET'),
        'token_ttl' => 3600,
        // redis | null  (null = tanpa publish, dipakai di test/dev tanpa Redis)
        'publish_driver' => env('INAPP_PUBLISH_DRIVER', 'redis'),
        'channel' => 'notifora:inapp-stream',
    ],

    // Resolusi DNS saat validasi URL webhook (nonaktif hanya di test)
    'ssrf_resolve' => env('SSRF_RESOLVE', true),

    'internal_secret' => env('INTERNAL_API_SECRET'),

    'queues' => [
        'high' => 'notifora_high',
        'default' => 'notifora_default',
        'channels' => 'notifora_channels',
        'webhooks' => 'notifora_webhooks',
    ],

    // Supervisor bawaan (php artisan notifora:supervise). Jumlah proses per antrean + batas hidup worker.
    'workers' => [
        'procs' => [
            'high' => (int) env('WORKER_PROCS_HIGH', 1),
            'default' => (int) env('WORKER_PROCS_DEFAULT', 2),
            'channels' => (int) env('WORKER_PROCS_CHANNELS', 3),
            'webhooks' => (int) env('WORKER_PROCS_WEBHOOKS', 1),
        ],
        'max_time' => (int) env('WORKER_MAX_TIME', 3600),   // detik; worker di-recycle berkala
        'memory' => (int) env('WORKER_MEMORY', 256),         // MB
        'tries' => (int) env('WORKER_TRIES', 3),
        'sleep' => (int) env('WORKER_SLEEP', 3),
        // Mode tanpa daemon (hosting bersama): scheduler menjalankan worker singkat tiap menit
        'cron_fallback' => (bool) env('WORKER_CRON_FALLBACK', false),
        // Antrean menumpuk lebih lama dari ini (detik) dianggap bermasalah oleh watchdog
        'backlog_alert_after' => (int) env('WORKER_BACKLOG_ALERT_AFTER', 600),
    ],

    // Backup database terjadwal (php artisan notifora:backup)
    'backup' => [
        'enabled' => (bool) env('BACKUP_ENABLED', true),
        'keep' => (int) env('BACKUP_KEEP', 14),
        'path' => env('BACKUP_PATH'),           // default storage/app/backups
        'mysqldump' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    ],
];
