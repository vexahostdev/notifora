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

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'suspended_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
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

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    /** Peran operator: null di DB dianggap super (kompatibel dengan operator lama). */
    public function adminRole(): string
    {
        $r = $this->admin_role;

        return ($r && array_key_exists($r, config('admin.roles'))) ? $r : 'super';
    }

    /** Boleh membuka bagian panel admin tertentu (lihat config admin.roles). */
    public function canAdmin(string $section): bool
    {
        if (! $this->isPlatformAdmin()) {
            return false;
        }
        $can = config('admin.roles.'.$this->adminRole().'.can', []);

        return in_array('*', $can, true) || in_array($section, $can, true) || in_array($section, ['dashboard', 'search'], true);
    }
}
