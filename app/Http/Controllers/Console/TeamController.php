<?php

namespace App\Http\Controllers\Console;

use App\Enums\MemberRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Member;
use App\Services\InvitationService;
use App\Services\SystemNotifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index()
    {
        $ws = app('current.workspace');

        return view('console.settings.team', [
            'members' => Member::with('user')->where('workspace_id', $ws->id)->orderBy('created_at')->get(),
            'invites' => Invitation::where('workspace_id', $ws->id)->whereNull('accepted_at')->where('expires_at', '>', now())->get(),
            'roles' => MemberRole::cases(),
        ]);
    }

    public function invite(Request $request, InvitationService $invites)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'role' => ['required', Rule::enum(MemberRole::class)]]);
        $role = MemberRole::from($data['role']);
        $this->assertCanAssign($role);

        $ws = app('current.workspace');
        if (Member::where('workspace_id', $ws->id)->whereHas('user', fn ($q) => $q->where('email', $data['email']))->exists()) {
            return back()->withErrors(['email' => 'Pengguna ini sudah menjadi anggota.']);
        }
        $invites->invite($ws, $data['email'], $role, $request->user());
        AuditLog::record($ws->id, 'member.invited', 'invitation', null, ['email' => $data['email'], 'role' => $role->value]);

        return back()->with('success', 'Undangan dikirim ke '.$data['email'].'.');
    }

    public function update(Request $request, Member $member)
    {
        $this->authorizeMember($member);
        $data = $request->validate(['role' => ['required', Rule::enum(MemberRole::class)]]);
        $role = MemberRole::from($data['role']);
        $this->assertCanAssign($role);

        if ($member->role === MemberRole::OWNER && $role !== MemberRole::OWNER && $this->ownerCount() <= 1) {
            return back()->with('error', 'Owner terakhir tidak dapat diturunkan.');
        }
        abort_if($member->role === MemberRole::OWNER && ! app('current.role')->atLeast(MemberRole::OWNER), 403);

        $member->update(['role' => $role]);
        AuditLog::record($member->workspace_id, 'member.role_changed', 'user', (string) $member->user_id, ['role' => $role->value]);
        if ($member->user_id !== auth()->id()) {
            app(SystemNotifier::class)->roleChanged(app('current.workspace'), $member->user, $role->value, auth()->user()->name);
        }

        return back()->with('success', 'Role diperbarui.');
    }

    public function destroy(Member $member)
    {
        $this->authorizeMember($member);
        if ($member->role === MemberRole::OWNER && $this->ownerCount() <= 1) {
            return back()->with('error', 'Owner terakhir tidak dapat dihapus.');
        }
        abort_if($member->role === MemberRole::OWNER && ! app('current.role')->atLeast(MemberRole::OWNER), 403);

        AuditLog::record($member->workspace_id, 'member.removed', 'user', (string) $member->user_id);
        $removed = $member->user;
        $member->delete();
        app(SystemNotifier::class)->memberRemoved(app('current.workspace'), $removed);

        return back()->with('success', 'Anggota dihapus.');
    }

    public function cancelInvite(string $id)
    {
        Invitation::where('workspace_id', app('current.workspace')->id)->findOrFail($id)->delete();

        return back()->with('success', 'Undangan dibatalkan.');
    }

    private function authorizeMember(Member $member): void
    {
        abort_unless($member->workspace_id === app('current.workspace')->id, 404);
    }

    private function ownerCount(): int
    {
        return Member::where('workspace_id', app('current.workspace')->id)->where('role', MemberRole::OWNER->value)->count();
    }

    /** Hanya OWNER yang boleh memberi role OWNER. */
    private function assertCanAssign(MemberRole $role): void
    {
        abort_if($role === MemberRole::OWNER && ! app('current.role')->atLeast(MemberRole::OWNER), 403, 'Hanya Owner yang dapat menetapkan role Owner.');
    }
}
