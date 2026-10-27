<?php

namespace App\Mail;

use App\Models\ApiKey;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * Katalog email sistem Notifora. Pengirim: noreply@notifora.my.id, balasan: support@notifora.my.id.
 *
 *  Akun       : welcome, resetPassword, passwordChanged
 *  Tim        : invitation, memberJoined, roleChanged, memberRemoved
 *  Keamanan   : apiKeyCreated, apiKeyRevoked
 *  Operasional: quotaWarning, webhookDisabled, providerDegraded
 *  Billing    : invoiceCreated, paymentSuccess, paymentFailed, subscriptionExpiring,
 *               subscriptionExpired, subscriptionDowngraded
 */
final class Mails
{
    private static function rp(int|float $n): string
    {
        return 'Rp '.number_format($n, 0, ',', '.');
    }

    private static function dt(?Carbon $d): string
    {
        return $d ? $d->copy()->setTimezone('Asia/Jakarta')->translatedFormat('d F Y, H:i').' WIB' : '-';
    }

    private static function console(string $path = ''): string
    {
        return rtrim(config('app.url'), '/').'/console'.$path;
    }

    // ---------------------------------------------------------------- Akun

    public static function welcome(User $user): SystemMail
    {
        return new SystemMail('Selamat datang di Notifora', [
            'title' => 'Selamat datang, '.$user->name.'!',
            'preheader' => 'Akun Notifora Anda sudah aktif. Ini langkah pertamanya.',
            'tone' => 'success',
            'paragraphs' => [
                'Akun Anda sudah aktif. Notifora membantu Anda mengirim notifikasi WhatsApp, Email, Telegram, dan In-App Inbox lewat satu API.',
                'Tiga langkah untuk mulai: (1) hubungkan channel di menu Channels, (2) buat dan publish workflow, (3) buat API key lalu panggil POST /v1/events/trigger.',
            ],
            'cta' => ['label' => 'Buka Console', 'url' => self::console()],
            'note' => 'Dokumentasi lengkap: '.rtrim(config('app.url'), '/').'/docs',
        ], 'welcome');
    }

    public static function resetPassword(string $url, int $minutes): SystemMail
    {
        return new SystemMail('Atur ulang password Notifora', [
            'title' => 'Atur ulang password',
            'preheader' => 'Tautan berlaku '.$minutes.' menit.',
            'paragraphs' => [
                'Kami menerima permintaan untuk mengatur ulang password akun Notifora Anda.',
                "Tautan di bawah berlaku {$minutes} menit dan hanya bisa dipakai sekali.",
            ],
            'cta' => ['label' => 'Atur ulang password', 'url' => $url],
            'note' => 'Jika Anda tidak merasa meminta ini, abaikan email ini; password Anda tidak berubah.',
        ], 'password-reset');
    }

    public static function passwordChanged(User $user, ?string $ip = null): SystemMail
    {
        return new SystemMail('Password akun Notifora Anda telah diubah', [
            'title' => 'Password berhasil diubah',
            'tone' => 'warn',
            'paragraphs' => ['Password akun '.$user->email.' baru saja diubah.'],
            'details' => array_filter(['Waktu' => self::dt(now()), 'Alamat IP' => $ip]),
            'note' => 'Jika bukan Anda yang melakukannya, segera atur ulang password dan hubungi '.config('notifora.support_email').'.',
        ], 'password-changed');
    }

    // ----------------------------------------------------------------- Tim

    public static function invitation(Workspace $ws, string $inviter, string $role, string $url, Carbon $expires): SystemMail
    {
        return new SystemMail("Undangan bergabung ke {$ws->name} di Notifora", [
            'title' => 'Anda diundang bergabung',
            'preheader' => "{$inviter} mengundang Anda ke {$ws->name}.",
            'paragraphs' => ["{$inviter} mengundang Anda bergabung ke workspace {$ws->name} di Notifora."],
            'details' => ['Workspace' => $ws->name, 'Peran' => $role, 'Berlaku sampai' => self::dt($expires)],
            'cta' => ['label' => 'Terima undangan', 'url' => $url],
            'note' => 'Gunakan email yang diundang saat masuk atau mendaftar. Abaikan email ini jika Anda tidak mengenal pengirimnya.',
        ], 'invitation');
    }

    public static function memberJoined(Workspace $ws, string $memberName, string $memberEmail, string $role): SystemMail
    {
        return new SystemMail("{$memberName} bergabung ke {$ws->name}", [
            'title' => 'Anggota baru bergabung',
            'tone' => 'success',
            'paragraphs' => ["{$memberName} menerima undangan dan kini menjadi anggota {$ws->name}."],
            'details' => ['Nama' => $memberName, 'Email' => $memberEmail, 'Peran' => $role],
            'cta' => ['label' => 'Kelola tim', 'url' => self::console('/settings/team')],
        ], 'member-joined');
    }

    public static function roleChanged(Workspace $ws, string $role, ?string $by = null): SystemMail
    {
        return new SystemMail("Peran Anda di {$ws->name} diubah", [
            'title' => 'Peran Anda diperbarui',
            'paragraphs' => ["Peran Anda di workspace {$ws->name} sekarang: {$role}."],
            'details' => array_filter(['Workspace' => $ws->name, 'Peran baru' => $role, 'Diubah oleh' => $by]),
            'cta' => ['label' => 'Buka Console', 'url' => self::console()],
        ], 'role-changed');
    }

    public static function memberRemoved(Workspace $ws): SystemMail
    {
        return new SystemMail("Akses Anda ke {$ws->name} dicabut", [
            'title' => 'Akses workspace dicabut',
            'tone' => 'warn',
            'paragraphs' => ["Anda tidak lagi menjadi anggota workspace {$ws->name} di Notifora."],
            'note' => 'Jika ini keliru, hubungi pemilik workspace atau '.config('notifora.support_email').'.',
        ], 'member-removed');
    }

    // ------------------------------------------------------------ Keamanan

    public static function apiKeyCreated(Workspace $ws, ApiKey $key, string $envName, string $actor, ?string $ip): SystemMail
    {
        return new SystemMail("API key baru dibuat di {$ws->name}", [
            'title' => 'API key baru dibuat',
            'tone' => 'warn',
            'paragraphs' => ["{$actor} membuat API key baru. Key lengkap hanya ditampilkan sekali saat dibuat."],
            'details' => array_filter(['Workspace' => $ws->name, 'Environment' => $envName, 'Nama key' => $key->name, 'Awalan' => $key->key_prefix.'…', 'Alamat IP' => $ip, 'Waktu' => self::dt(now())]),
            'cta' => ['label' => 'Tinjau API key', 'url' => self::console('/settings/api-keys')],
            'note' => 'Jika bukan tindakan yang Anda kenal, cabut key tersebut dan ganti password.',
        ], 'apikey-created');
    }

    public static function apiKeyRevoked(Workspace $ws, ApiKey $key, string $envName, string $actor): SystemMail
    {
        return new SystemMail("API key dicabut di {$ws->name}", [
            'title' => 'API key dicabut',
            'paragraphs' => ["{$actor} mencabut API key. Aplikasi yang memakainya akan menerima 401."],
            'details' => ['Workspace' => $ws->name, 'Environment' => $envName, 'Nama key' => $key->name, 'Waktu' => self::dt(now())],
            'cta' => ['label' => 'Tinjau API key', 'url' => self::console('/settings/api-keys')],
        ], 'apikey-revoked');
    }

    // --------------------------------------------------------- Operasional

    public static function quotaWarning(Workspace $ws, int $percent, int $used, int $limit): SystemMail
    {
        $over = $percent >= 100;

        return new SystemMail($over ? "Kuota event {$ws->name} habis" : "Kuota event {$ws->name} mencapai {$percent}%", [
            'title' => $over ? 'Kuota event bulan ini habis' : "Kuota event mencapai {$percent}%",
            'tone' => $over ? 'danger' : 'warn',
            'paragraphs' => [
                $over
                    ? 'Anda telah melewati kuota event bulanan. Ada toleransi 10%; setelah itu event baru ditolak (HTTP 402) sampai bulan berikutnya atau plan di-upgrade.'
                    : 'Pemakaian event bulan ini sudah mendekati batas plan Anda. Upgrade sekarang agar pengiriman tidak terhenti.',
            ],
            'details' => ['Workspace' => $ws->name, 'Plan' => $ws->planConfig()['label'], 'Terpakai' => number_format($used, 0, ',', '.').' / '.number_format($limit, 0, ',', '.')],
            'cta' => ['label' => 'Upgrade plan', 'url' => self::console('/billing')],
        ], 'quota-'.($over ? '100' : '80'));
    }

    public static function webhookDisabled(Workspace $ws, string $url, int $failures): SystemMail
    {
        return new SystemMail('Webhook dinonaktifkan karena gagal berulang', [
            'title' => 'Webhook dinonaktifkan otomatis',
            'tone' => 'danger',
            'paragraphs' => ['Endpoint webhook Anda gagal menerima callback berturut-turut sehingga dinonaktifkan agar tidak membebani sistem.'],
            'details' => ['Workspace' => $ws->name, 'Endpoint' => $url, 'Gagal beruntun' => (string) $failures],
            'cta' => ['label' => 'Periksa webhook', 'url' => self::console('/settings/webhooks')],
            'note' => 'Perbaiki endpoint Anda lalu aktifkan kembali dari halaman Webhooks.',
        ], 'webhook-disabled');
    }

    public static function providerDegraded(Workspace $ws, string $channel, string $providerName): SystemMail
    {
        return new SystemMail("Provider {$channel} bermasalah", [
            'title' => 'Provider pengiriman bermasalah',
            'tone' => 'warn',
            'paragraphs' => ["Provider {$providerName} ({$channel}) gagal berulang kali. Pengiriman ke channel ini dijeda sementara dan dijadwal ulang otomatis."],
            'details' => ['Workspace' => $ws->name, 'Channel' => $channel, 'Provider' => $providerName],
            'cta' => ['label' => 'Periksa channel', 'url' => self::console('/channels')],
            'note' => 'Pastikan kredensial dan endpoint provider benar. Pesan tidak hilang dan dicoba lagi saat provider pulih.',
        ], 'provider-degraded');
    }

    // ------------------------------------------------------------- Billing

    private static function paymentDetails(Payment $p): array
    {
        return [
            'No. invoice' => $p->number,
            'Plan' => $p->planLabel().' · '.$p->months.' bulan',
            'Total' => self::rp($p->amount),
        ];
    }

    public static function invoiceCreated(Workspace $ws, Payment $p): SystemMail
    {
        return new SystemMail("Invoice {$p->number} — selesaikan pembayaran", [
            'title' => 'Invoice menunggu pembayaran',
            'preheader' => 'Bayar sebelum '.self::dt($p->expires_at),
            'paragraphs' => ["Invoice untuk workspace {$ws->name} sudah dibuat. Selesaikan pembayaran sebelum jatuh tempo agar plan aktif."],
            'details' => self::paymentDetails($p) + ['Jatuh tempo' => self::dt($p->expires_at)],
            'cta' => ['label' => 'Bayar sekarang', 'url' => $p->invoice_url ?: self::console('/billing/payments/'.$p->id)],
            'note' => 'Plan aktif otomatis setelah pembayaran terkonfirmasi. Anda bisa menutup halaman pembayaran dan kembali kapan saja sebelum jatuh tempo.',
        ], 'invoice-created');
    }

    public static function paymentSuccess(Workspace $ws, Payment $p): SystemMail
    {
        return new SystemMail("Pembayaran {$p->number} berhasil", [
            'title' => 'Pembayaran diterima',
            'tone' => 'success',
            'preheader' => 'Plan '.$p->planLabel().' sudah aktif.',
            'paragraphs' => ["Terima kasih! Pembayaran Anda terkonfirmasi dan plan {$p->planLabel()} sudah aktif untuk workspace {$ws->name}."],
            'details' => self::paymentDetails($p) + array_filter([
                'Metode' => $p->payment_channel ?: $p->payment_method,
                'Dibayar' => self::dt($p->paid_at),
                'Aktif sampai' => self::dt($ws->plan_expires_at),
            ]),
            'cta' => ['label' => 'Lihat bukti pembayaran', 'url' => self::console('/billing/payments/'.$p->id)],
        ], 'payment-success');
    }

    public static function paymentFailed(Workspace $ws, Payment $p): SystemMail
    {
        $expired = $p->status === Payment::EXPIRED;

        return new SystemMail($expired ? "Invoice {$p->number} kedaluwarsa" : "Pembayaran {$p->number} gagal", [
            'title' => $expired ? 'Invoice kedaluwarsa' : 'Pembayaran gagal',
            'tone' => 'danger',
            'paragraphs' => [$expired
                ? 'Batas waktu pembayaran telah lewat. Tidak ada dana yang ditagihkan. Buat invoice baru untuk melanjutkan.'
                : 'Pembayaran tidak dapat diproses. Silakan coba lagi dengan metode lain.'],
            'details' => self::paymentDetails($p),
            'cta' => ['label' => 'Buat invoice baru', 'url' => self::console('/billing')],
        ], 'payment-failed');
    }

    public static function subscriptionExpiring(Workspace $ws, int $days): SystemMail
    {
        $when = $days <= 1 ? 'besok' : "dalam {$days} hari";

        return new SystemMail("Plan {$ws->planConfig()['label']} berakhir {$when}", [
            'title' => "Plan berakhir {$when}",
            'tone' => 'warn',
            'paragraphs' => ['Perpanjang sekarang agar kuota dan batas request tidak turun ke plan Free.'],
            'details' => ['Workspace' => $ws->name, 'Plan' => $ws->planConfig()['label'], 'Berakhir' => self::dt($ws->plan_expires_at)],
            'cta' => ['label' => 'Perpanjang plan', 'url' => self::console('/billing')],
        ], 'subscription-expiring');
    }

    public static function subscriptionExpired(Workspace $ws): SystemMail
    {
        $grace = (int) config('notifora.billing.grace_days');

        return new SystemMail("Plan {$ws->name} telah berakhir", [
            'title' => 'Plan berakhir — masa tenggang berjalan',
            'tone' => 'danger',
            'paragraphs' => ["Plan berbayar Anda sudah berakhir. Anda masih mendapat masa tenggang {$grace} hari dengan batas plan sebelumnya. Setelah itu workspace turun ke plan Free."],
            'details' => ['Workspace' => $ws->name, 'Berakhir' => self::dt($ws->plan_expires_at), 'Tenggang sampai' => self::dt($ws->graceEndsAt())],
            'cta' => ['label' => 'Perpanjang sekarang', 'url' => self::console('/billing')],
        ], 'subscription-expired');
    }

    public static function subscriptionDowngraded(Workspace $ws): SystemMail
    {
        $f = config('notifora.free_plan');

        return new SystemMail("Workspace {$ws->name} kini di plan Free", [
            'title' => 'Workspace turun ke plan Free',
            'tone' => 'danger',
            'paragraphs' => ['Masa tenggang berakhir tanpa perpanjangan. Data Anda aman; hanya batas yang berubah.'],
            'details' => ['Kuota event' => number_format($f['events'], 0, ',', '.').' / bulan', 'Rate limit' => $f['rpm'].' req/menit', 'Retensi log' => $f['retention_days'].' hari'],
            'cta' => ['label' => 'Aktifkan kembali plan', 'url' => self::console('/billing')],
        ], 'subscription-downgraded');
    }
}
