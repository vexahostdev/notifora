<?php

/**
 * Navigasi dokumentasi. Setiap section = satu halaman /docs/{slug}
 * (resources/views/docs/pages/{slug}.blade.php); setiap artikel punya anchor #id.
 * Format artikel: [id, judul, kata kunci pencarian].
 */
return [
    'version' => 'v1.0',
    'updated' => 'Oktober 2026',

    'sections' => [
        'mulai-cepat' => [
            'title' => 'Mulai Cepat & Orientasi',
            'icon' => 'play',
            'summary' => 'Apa itu Notifora, cara mengirim notifikasi pertama dalam lima menit, dan konsep inti yang perlu dipahami.',
            'articles' => [
                ['pengenalan', 'Pengenalan Notifora', 'gateway notifikasi satu api whatsapp email telegram inbox'],
                ['quickstart', 'Quickstart: Kirim Notifikasi Pertama', 'mulai cepat curl php node python trigger contoh'],
                ['konsep-inti', 'Konsep Inti', 'workspace environment subscriber workflow event eksekusi step'],
            ],
        ],
        'autentikasi' => [
            'title' => 'Autentikasi & Batas Pemakaian',
            'icon' => 'key',
            'summary' => 'API key, environment Development/Production, rate limit, kuota event, dan idempotency.',
            'articles' => [
                ['api-key', 'API Key & Header Authorization', 'bearer ntf_live ntf_test token otentikasi rahasia cabut'],
                ['environment', 'Environment Development & Production', 'dev prod sandbox terpisah data'],
                ['rate-limit', 'Rate Limit & Kuota Event', '429 402 limit per menit kuota bulanan plan'],
                ['idempotency', 'Idempotency Key', 'X-Idempotency-Key duplikat retry aman 409'],
            ],
        ],
        'referensi-api' => [
            'title' => 'Referensi API',
            'icon' => 'list',
            'summary' => 'Semua endpoint /v1: trigger, bulk, status event, subscriber, preferensi, inbox, log pesan, dan workflow.',
            'articles' => [
                ['format-respons', 'Format Respons & Kode Error', 'error code 401 403 404 422 429 402 request id'],
                ['trigger', 'POST /events/trigger', 'kirim notifikasi memicu workflow payload to subscriberId'],
                ['bulk', 'POST /events/bulk', 'banyak event sekaligus batch 100'],
                ['status-event', 'GET & DELETE /events/{id}', 'status eksekusi batalkan cancel step'],
                ['subscribers', 'Subscribers', 'upsert penerima email phone telegram hapus erasure'],
                ['preferensi', 'Preferensi Notifikasi', 'opt-out mute channel per workflow'],
                ['inbox-api', 'Inbox API', 'in-app list tandai dibaca unread cursor'],
                ['pesan', 'Log Pesan', 'messages status delivered failed percobaan'],
                ['daftar-workflow', 'GET /workflows', 'daftar workflow aktif trigger_key'],
            ],
        ],
        'workflow-template' => [
            'title' => 'Workflow & Template',
            'icon' => 'workflow',
            'summary' => 'Menyusun alur notifikasi: step, delay, filter, fallback, sintaks template, dan preferensi subscriber.',
            'articles' => [
                ['membuat-workflow', 'Membuat & Mempublish Workflow', 'draft publish versi trigger key aktif nonaktif'],
                ['tipe-step', 'Tipe Step: Channel, Delay, Filter', 'step urutan tunggu kondisi rule operator'],
                ['fallback', 'Fallback & Retry', 'gagal cadangan alternatif backoff percobaan ulang'],
                ['sintaks-template', 'Sintaks Template', 'variabel filter upper default currency date number truncate'],
                ['override', 'Override Template per Trigger', 'overrides channels subject body'],
                ['preferensi-optout', 'Preferensi & Opt-out', 'kritis otp global mute'],
            ],
        ],
        'channel-provider' => [
            'title' => 'Channel & Provider',
            'icon' => 'plug',
            'summary' => 'Menghubungkan WhatsApp (VexaHost), Email (Postivo), Telegram, dan In-App; serta status delivery masuk.',
            'articles' => [
                ['whatsapp', 'WhatsApp (VexaHost)', 'vexahost gateway session nomor 62'],
                ['email', 'Email (Postivo)', 'postivo smtp from html subject'],
                ['telegram', 'Telegram Bot', 'bot token chat id'],
                ['in-app', 'In-App Inbox', 'lonceng bell notifikasi dalam aplikasi'],
                ['status-delivery', 'Webhook Status Delivery', 'delivered read sent hooks token provider'],
            ],
        ],
        'inbox-realtime' => [
            'title' => 'Inbox Real-time',
            'icon' => 'bell',
            'summary' => 'Menampilkan bell notifikasi real-time di aplikasi Anda: token subscriber, widget, dan event WebSocket.',
            'articles' => [
                ['alur-token', 'Alur Token Subscriber', 'jwt inbox-token socket aman server'],
                ['widget', 'Widget Inbox', 'socket.io alpine react javascript integrasi'],
                ['event-websocket', 'Event WebSocket', 'unread:count notification:received mark_read auth:expired'],
            ],
        ],
        'webhook-keluar' => [
            'title' => 'Webhook Keluar',
            'icon' => 'webhook',
            'summary' => 'Menerima callback status pengiriman di server Anda dengan tanda tangan HMAC.',
            'articles' => [
                ['event-payload', 'Event & Payload', 'message.sent message.delivered execution.completed'],
                ['verifikasi-signature', 'Verifikasi Signature', 'hmac sha256 X-Notifora-Signature php node python'],
                ['retry-disable', 'Retry & Penonaktifan Otomatis', 'gagal ulang jadwal disable replay'],
            ],
        ],
        'billing-plan' => [
            'title' => 'Plan, Billing & Pembayaran',
            'icon' => 'card',
            'summary' => 'Plan dan kuota, checkout lewat Xendit, masa berlaku dan tenggang, serta email notifikasi sistem.',
            'articles' => [
                ['plan-kuota', 'Plan & Kuota', 'harga starter pro business scale free'],
                ['checkout', 'Checkout & Pembayaran', 'xendit qris virtual account invoice bayar upgrade perpanjang'],
                ['masa-berlaku', 'Masa Berlaku & Tenggang', 'kedaluwarsa pengingat downgrade free grace'],
                ['email-sistem', 'Email Notifikasi Sistem', 'brevo smtp noreply support undangan reset'],
            ],
        ],
        'operasional' => [
            'title' => 'Operasional & Keamanan',
            'icon' => 'activity',
            'summary' => 'Membaca status eksekusi, mengatasi masalah umum, dan praktik keamanan.',
            'articles' => [
                ['status-eksekusi', 'Status Eksekusi & Step', 'completed partial failed skipped waiting'],
                ['troubleshooting', 'Troubleshooting', 'masalah gagal tidak terkirim 401 402 429 provider circuit'],
                ['keamanan', 'Praktik Keamanan', 'secret rotasi ssrf https masking audit'],
            ],
        ],
    ],
];
