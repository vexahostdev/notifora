<?php

namespace App\Models;

use App\Enums\MemberRole;
use App\Mail\Mails;
use App\Services\SystemNotifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    /** Email reset password memakai layout bermerek (Brevo SMTP) lewat SystemNotifier. */
    public function sendPasswordResetNotification($token): void
    {
        $url = route('password.reset', ['token' => $token, 'email' => $this->email]);
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        app(SystemNotifier::class)->deliver($this->email, Mails::resetPassword($url, $minutes));
    }

    public function memberships()
    {
        return $this->hasMany(Member::class);
    }

    /** Workspace aktif (MVP: membership tertua user). */
    public function currentMembership(): ?Member
    {
        return $this->memberships()->with('workspace')->oldest()->first();
    }

    public function roleIn(string $workspaceId): ?MemberRole
    {
        return $this->memberships()->where('workspace_id', $workspaceId)->first()?->role;
    }
}
