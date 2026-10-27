<?php

namespace App\Services;

use App\Mail\Mails;
use App\Mail\SystemMail;
use App\Models\ApiKey;
use App\Models\ChannelProvider;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Environment;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Titik tunggal pengiriman email sistem. Kegagalan SMTP (mis. kredensial Brevo belum diisi)
 * dicatat ke log dan TIDAK PERNAH menggagalkan request pengguna atau job.
 */
class SystemNotifier
{
    /** @param string|array<int,string> $to */
    public function deliver(string|array $to, SystemMail $mail, ?string $workspaceId = null): void
    {
        $to = array_values(array_filter((array) $to));
        if (! $to) {
            return;
        }

        $log = null;
        try {
            $template = EmailTemplate::find($mail->kind);
            if ($template && ! $template->enabled) {
                EmailLog::create(['kind' => $mail->kind, 'recipient' => mb_substr(implode(', ', $to), 0, 255), 'subject' => $mail->mailSubject, 'status' => 'skipped', 'workspace_id' => $workspaceId]);

                return;
            }
            $template && $mail->applyTemplate($template);
            $log = EmailLog::create(['kind' => $mail->kind, 'recipient' => mb_substr(implode(', ', $to), 0, 255), 'subject' => $mail->mailSubject, 'status' => 'queued', 'workspace_id' => $workspaceId]);
            $mail->logId = $log->id;
        } catch (Throwable $e) {
            Log::warning('system_mail.log_failed', ['error' => $e->getMessage()]); // pencatatan tidak boleh menghalangi pengiriman
        }

        try {
            Mail::to($to)->send($mail); // ShouldQueue: masuk antrean bila worker aktif, langsung kirim bila sync
        } catch (Throwable $e) {
            $log?->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            Log::warning('system_mail.failed', ['kind' => $mail->kind, 'error' => $e->getMessage()]);
        }
    }

    // ---- Akun ------------------------------------------------------------

    public function welcome(User $user): void
    {
        $this->deliver($user->email, Mails::welcome($user));
    }

    public function passwordChanged(User $user): void
    {
        $this->deliver($user->email, Mails::passwordChanged($user, request()?->ip()));
    }

    // ---- Tim -------------------------------------------------------------

    public function memberJoined(Workspace $ws, User $member, string $role, ?User $inviter): void
    {
        $to = $inviter ? [$inviter->email] : $ws->adminEmails();
        $this->deliver($to, Mails::memberJoined($ws, $member->name, $member->email, $role));
    }

    public function roleChanged(Workspace $ws, User $member, string $role, ?string $by): void
    {
        $this->deliver($member->email, Mails::roleChanged($ws, $role, $by));
    }

    public function memberRemoved(Workspace $ws, User $member): void
    {
        $this->deliver($member->email, Mails::memberRemoved($ws));
    }

    // ---- Keamanan --------------------------------------------------------

    public function apiKeyCreated(Workspace $ws, ApiKey $key, Environment $env, ?User $actor): void
    {
        $this->deliver($ws->adminEmails(), Mails::apiKeyCreated($ws, $key, $env->name, $actor?->name ?? 'Sistem', request()?->ip()));
    }

    public function apiKeyRevoked(Workspace $ws, ApiKey $key, Environment $env, ?User $actor): void
    {
        $this->deliver($ws->adminEmails(), Mails::apiKeyRevoked($ws, $key, $env->name, $actor?->name ?? 'Sistem'));
    }

    // ---- Operasional -----------------------------------------------------

    /** Kirim sekali per bulan per level (80% dan 100%) untuk environment production. */
    public function quotaThreshold(Environment $env, int $used, int $limit): void
    {
        if (! $env->isProduction() || $limit <= 0) {
            return;
        }
        $percent = (int) floor($used / $limit * 100);
        foreach ([100, 80] as $level) {
            if ($percent >= $level && Cache::add("quota-mail:{$env->id}:".now()->format('Ym').":$level", 1, now()->addDays(40))) {
                $ws = $env->workspace;
                $this->deliver($ws->adminEmails(), Mails::quotaWarning($ws, $level, $used, $limit));
                break;
            }
        }
    }

    public function webhookDisabled(Workspace $ws, string $url, int $failures): void
    {
        $this->deliver($ws->adminEmails(), Mails::webhookDisabled($ws, $url, $failures));
    }

    /** Maksimal 1 email per provider per jam. */
    public function providerDegraded(ChannelProvider $provider): void
    {
        if (! Cache::add("provider-mail:{$provider->id}", 1, now()->addHour())) {
            return;
        }
        $ws = Environment::with('workspace')->find($provider->environment_id)?->workspace;
        if ($ws) {
            $this->deliver($ws->adminEmails(), Mails::providerDegraded($ws, $provider->channel->label(), $provider->name));
        }
    }

    // ---- Billing ---------------------------------------------------------

    public function invoiceCreated(Workspace $ws, Payment $p, ?User $user): void
    {
        $this->deliver(array_unique(array_filter([$user?->email, ...$ws->adminEmails()])), Mails::invoiceCreated($ws, $p));
    }

    public function paymentSuccess(Workspace $ws, Payment $p): void
    {
        $this->deliver($this->billingRecipients($ws, $p), Mails::paymentSuccess($ws, $p));
    }

    public function paymentFailed(Workspace $ws, Payment $p): void
    {
        $this->deliver($this->billingRecipients($ws, $p), Mails::paymentFailed($ws, $p));
    }

    public function subscriptionExpiring(Workspace $ws, int $days): void
    {
        $this->deliver($ws->adminEmails(), Mails::subscriptionExpiring($ws, $days));
    }

    public function subscriptionExpired(Workspace $ws): void
    {
        $this->deliver($ws->adminEmails(), Mails::subscriptionExpired($ws));
    }

    public function subscriptionDowngraded(Workspace $ws): void
    {
        $this->deliver($ws->adminEmails(), Mails::subscriptionDowngraded($ws));
    }

    private function billingRecipients(Workspace $ws, Payment $p): array
    {
        return array_values(array_unique(array_filter([$p->user?->email, ...$ws->adminEmails()])));
    }
}
