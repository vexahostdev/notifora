<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAudit;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    /** Keluar dari mode impersonasi dan kembali sebagai operator. Berada di luar grup admin agar bisa dipanggil dari Console. */
    public function stop(Request $request)
    {
        $adminId = $request->session()->get('impersonator_id');
        $admin = $adminId ? User::find($adminId) : null;
        abort_unless($admin && $admin->isPlatformAdmin(), 403);

        $target = $request->user();
        AdminAudit::record('impersonation.stopped', 'user', (string) $target->id, $target->currentMembership()?->workspace_id, ['email' => $target->email]);

        $request->session()->forget(['impersonator_id', 'environment_id']);
        auth()->login($admin);

        return redirect()->route('admin.tenants.index')->with('success', 'Impersonasi selesai.');
    }
}
