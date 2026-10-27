<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\Mails;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Services\AdminAudit;
use App\Services\Brand;
use App\Services\MailSettings;
use App\Services\PlatformSettings;
use App\Services\SystemNotifier;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    public function logs(Request $request)
    {
        $q = EmailLog::with('workspace:id,name')->latest('id');
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }
        if ($k = $request->query('kind')) {
            $q->where('kind', $k);
        }
        if ($r = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('recipient', 'like', "%$r%")->orWhere('subject', 'like', "%$r%"));
        }

        return view('admin.emails.logs', [
            'logs' => $q->paginate(40)->withQueryString(),
            'kinds' => config('admin.email_kinds'),
            'counts' => EmailLog::where('created_at', '>=', now()->subDay())->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'mailer' => config('mail.default'),
        ]);
    }

    public function templates()
    {
        return view('admin.emails.templates', [
            'kinds' => config('admin.email_kinds'),
            'templates' => EmailTemplate::all()->keyBy('kind'),
        ]);
    }

    public function templateUpdate(Request $request, string $kind)
    {
        abort_unless(array_key_exists($kind, config('admin.email_kinds')), 404);
        $d = $request->validate([
            'subject_prefix' => ['nullable', 'string', 'max:60'],
            'extra_note' => ['nullable', 'string', 'max:1000'],
        ]);
        // Email keamanan inti tidak boleh dimatikan
        $locked = in_array($kind, config('admin.email_locked'), true);
        EmailTemplate::updateOrCreate(['kind' => $kind], [
            'enabled' => $locked ? true : $request->boolean('enabled'),
            'subject_prefix' => $d['subject_prefix'] ?? null,
            'extra_note' => $d['extra_note'] ?? null,
        ]);
        AdminAudit::record('email.template_saved', 'email_template', $kind, null, ['enabled' => $request->boolean('enabled'), 'prefix' => $d['subject_prefix'] ?? null]);

        return back()->with('success', 'Template email disimpan.');
    }

    // --- Konfigurasi SMTP (disimpan di DB, password terenkripsi) ---------------------

    public function smtp()
    {
        return view('admin.emails.smtp', [
            'cfg' => MailSettings::effective(),
            'override' => MailSettings::overridden(),
            'mailer' => config('mail.default'),
            'supportEmail' => Brand::supportEmail(),
        ]);
    }

    public function smtpUpdate(Request $request)
    {
        $d = $request->validate([
            'host' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:300'],
            'encryption' => ['required', 'in:smtp,smtps'],
            'from_address' => ['required', 'email', 'max:120'],
            'from_name' => ['nullable', 'string', 'max:60'],
        ]);

        PlatformSettings::set('mail_host', $d['host']);
        PlatformSettings::set('mail_port', (string) $d['port']);
        PlatformSettings::set('mail_username', $d['username'] ?? '');
        PlatformSettings::set('mail_encryption', $d['encryption']);
        PlatformSettings::set('mail_from_address', $d['from_address']);
        PlatformSettings::set('mail_from_name', $d['from_name'] ?? '');
        if (! empty($d['password'])) {
            MailSettings::savePassword($d['password']);
        }
        PlatformSettings::setBool('mail_override', $request->boolean('override'));
        AdminAudit::record('email.smtp_saved', null, null, null, ['host' => $d['host'], 'port' => $d['port'], 'override' => $request->boolean('override'), 'password_changed' => ! empty($d['password'])]);

        MailSettings::apply();

        return back()->with('success', $request->boolean('override') ? 'Konfigurasi SMTP disimpan dan sekarang dipakai menggantikan .env.' : 'Konfigurasi disimpan, tetapi belum aktif (centang "Pakai konfigurasi ini").');
    }

    /** Uji koneksi & login SMTP tanpa mengirim email. */
    public function smtpProbe()
    {
        $r = MailSettings::probe();
        AdminAudit::record('email.smtp_probed', null, null, null, ['ok' => $r['ok']]);

        return back()->with($r['ok'] ? 'success' : 'error', ($r['ok'] ? '✔ ' : '✖ ').$r['message'].' ('.$r['ms'].' ms)');
    }

    /** Kirim email uji ke alamat operator untuk memverifikasi kredensial SMTP (Brevo). */
    public function test(Request $request, SystemNotifier $notifier)
    {
        $to = $request->validate(['to' => ['required', 'email']])['to'];
        $notifier->deliver($to, Mails::smtpTest($request->user()->name));
        AdminAudit::record('email.smtp_tested', null, null, null, ['to' => $to, 'mailer' => config('mail.default')]);

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->with('error', 'Mailer aktif "'.config('mail.default').'": email hanya ditulis ke log, TIDAK dikirim. Isi SMTP di Admin → SMTP & uji kirim (centang "Pakai konfigurasi ini") atau set MAIL_MAILER=smtp.');
        }

        return back()->with('success', "Email uji dikirim ke {$to} (mailer: ".config('mail.default').'). Periksa status di daftar log.');
    }
}
