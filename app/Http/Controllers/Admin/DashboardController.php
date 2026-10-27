<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlatformMetrics;

class DashboardController extends Controller
{
    public function index(PlatformMetrics $metrics)
    {
        return view('admin.dashboard', $metrics->overview());
    }
}
