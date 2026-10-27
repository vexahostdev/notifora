<?php

namespace App\Services;

use App\Enums\MemberRole;
use App\Mail\Mails;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

class InvitationService
{
    public function __construct(private SystemNotifier $notifier) {}

    /** Buat undangan dan kirim email. @return string token mentah */
    public function invite(Workspace $workspace, string $email, MemberRole $role, ?User $inviter = null): string
    {
        $token = Str::random(48);
        Invitation::where('workspace_id', $workspace->id)->where('email', $email)->whereNull('accepted_at')->delete();
        $invitation = Invitation::create([
            'workspace_id' => $workspace->id,
            'email' => $email,
            'role' => $role->value,
            'invited_by' => $inviter?->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);

        $this->notifier->deliver($email, Mails::invitation(
            $workspace,
            $inviter?->name ?? 'Tim '.$workspace->name,
            $role->value,
            route('invitation.accept', $token),
            $invitation->expires_at,
        ));

        return $token;
    }

    public function find(string $token): ?Invitation
    {
        $inv = Invitation::where('token_hash', hash('sha256', $token))->first();

        return $inv && ! $inv->accepted_at && $inv->expires_at->isFuture() ? $inv : null;
    }

    /** Terima undangan untuk user (email harus cocok). */
    public function accept(User $user, string $token): ?Member
    {
        $inv = $this->find($token);
        if (! $inv || strcasecmp($inv->email, $user->email) !== 0) {
            return null;
        }

        $member = Member::firstOrCreate(
            ['workspace_id' => $inv->workspace_id, 'user_id' => $user->id],
            ['role' => $inv->role],
        );
        $inv->update(['accepted_at' => now()]);
        AuditLog::record($inv->workspace_id, 'member.joined', 'user', (string) $user->id, ['role' => $inv->role]);

        $workspace = Workspace::find($inv->workspace_id);
        if ($workspace) {
            $this->notifier->memberJoined($workspace, $user, $inv->role, $inv->invited_by ? User::find($inv->invited_by) : null);
        }

        return $member;
    }
}
