<?php

namespace App\Services;

use App\Enums\EnvironmentType;
use App\Enums\MemberRole;
use App\Models\Environment;
use App\Models\Member;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceProvisioner
{
    /** Buat workspace + environment DEV & PROD + membership OWNER. */
    public function create(User $owner, string $name): Workspace
    {
        return DB::transaction(function () use ($owner, $name) {
            $workspace = Workspace::create([
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
                'plan' => 'free',
                'billing_email' => $owner->email,
                'owner_id' => $owner->id,
            ]);

            foreach (EnvironmentType::cases() as $type) {
                Environment::create([
                    'workspace_id' => $workspace->id,
                    'type' => $type,
                    'name' => ucfirst(strtolower($type->value)),
                ]);
            }

            Member::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => MemberRole::OWNER]);

            return $workspace;
        });
    }
}
