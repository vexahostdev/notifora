<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\Mails;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Services\AdminAudit;
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

    /** Kirim email uji ke alamat operator untuk memverifikasi kredensial SMTP (Brevo). */
    public function test(Request $request, SystemNotifier $notifier)
    {
        $to = $request->validate(['to' => ['required', 'email']])['to'];
        $notifier->deliver($to, Mails::smtpTest($request->user()->name));
        AdminAudit::record('email.smtp_tested', null, null, null, ['to' => $to, 'mailer' => config('mail.default')]);

        return back()->with('success', "Email uji dikirim ke {$to} (mailer: ".config('mail.default').'). Periksa status di daftar log.');
    }
}
