<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\AdminAudit;
use App\Services\PlatformMetrics;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(PlatformMetrics $metrics)
    {
        $paidWs = Workspace::where('plan', '!=', 'free')->count();

        return view('admin.reports.index', [
            'revenue' => $metrics->revenueReport(),
            'top' => $metrics->topTenants(10),
            'mrr' => $metrics->mrr(),
            'arr' => $metrics->mrr() * 12,
            'distribution' => Workspace::selectRaw('plan, count(*) c')->groupBy('plan')->pluck('c', 'plan'),
            'paidWorkspaces' => $paidWs,
            'total' => Workspace::count(),
            'expiring' => Workspace::where('plan', '!=', 'free')->whereBetween('plan_expires_at', [now(), now()->addDays(14)])->orderBy('plan_expires_at')->limit(10)->get(['id', 'name', 'plan', 'plan_expires_at']),
        ]);
    }

    public function workspacesCsv(Request $request): StreamedResponse
    {
        AdminAudit::record('report.workspaces_exported');

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'name', 'slug', 'owner_email', 'plan', 'plan_expires_at', 'suspended', 'members', 'created_at']);
            Workspace::with('owner:id,email')->withCount('members')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $w) {
                    fputcsv($out, [$w->id, $w->name, $w->slug, $w->owner?->email, $w->plan, $w->plan_expires_at, $w->isSuspended() ? 'yes' : 'no', $w->members_count, $w->created_at]);
                }
            });
            fclose($out);
        }, 'notifora-workspaces-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
