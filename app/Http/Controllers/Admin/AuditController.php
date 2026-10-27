<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformAuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $scope = $request->query('scope') === 'tenants' ? 'tenants' : 'platform';

        if ($scope === 'tenants') {
            $q = AuditLog::with('user:id,name', 'workspace:id,name')->latest('id');
            if ($w = $request->query('workspace')) {
                $q->where('workspace_id', $w);
            }
        } else {
            $q = PlatformAuditLog::with('admin:id,name,email', 'workspace:id,name')->latest('id');
            if ($w = $request->query('workspace')) {
                $q->where('workspace_id', $w);
            }
            if ($a = $request->query('admin')) {
                $q->where('admin_id', $a);
            }
        }
        if ($act = trim((string) $request->query('action'))) {
            $q->where('action', 'like', "$act%");
        }

        return view('admin.audit', ['logs' => $q->paginate(40)->withQueryString(), 'scope' => $scope]);
    }
}
