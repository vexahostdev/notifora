<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ChannelProvider;
use App\Models\MessageLog;
use App\Models\Workflow;
use App\Services\DashboardMetrics;
use App\Services\UsageMeter;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(DashboardMetrics $metrics, UsageMeter $usage)
    {
        $env = app('current.environment');
        $limit = $usage->limitFor($env);
        $used = $usage->monthlyEvents($env);

        return view('console.dashboard', [
            'data' => $metrics->summary('24h'),
            'usage' => ['used' => $used, 'limit' => $limit, 'pct' => $limit ? min(100, round($used / $limit * 100)) : 0],
            'onboarding' => [
                ['done' => ChannelProvider::exists(), 'label' => 'Hubungkan minimal 1 channel', 'url' => route('console.channels.index')],
                ['done' => Workflow::where('status', 'ACTIVE')->exists(), 'label' => 'Buat & publish workflow', 'url' => route('console.workflows.index')],
                ['done' => ApiKey::whereNull('revoked_at')->whereHas('environment', fn ($q) => $q->where('id', $env->id))->exists(), 'label' => 'Buat API key', 'url' => route('console.api-keys.index')],
                ['done' => MessageLog::exists(), 'label' => 'Kirim trigger pertama', 'url' => route('console.workflows.index')],
            ],
        ]);
    }

    public function data(Request $request, DashboardMetrics $metrics)
    {
        $range = in_array($request->query('range'), ['24h', '7d', '30d'], true) ? $request->query('range') : '24h';

        return response()->json($metrics->summary($range));
    }
}
