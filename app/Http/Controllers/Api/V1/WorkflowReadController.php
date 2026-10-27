<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Support\ApiResponse;

class WorkflowReadController extends Controller
{
    public function index()
    {
        $items = Workflow::with('publishedVersion')->where('status', 'ACTIVE')->orderBy('name')->get()->map(fn ($w) => [
            'name' => $w->name,
            'trigger_key' => $w->trigger_key,
            'priority' => $w->priority,
            'version' => $w->publishedVersion?->version,
            'channels' => collect($w->publishedVersion?->snapshot['steps'] ?? [])->pluck('channel')->filter()->unique()->values(),
        ]);

        return ApiResponse::ok($items);
    }
}
