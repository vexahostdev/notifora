<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Services\SystemNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ApiKeyController extends Controller
{
    public function index()
    {
        $env = app('current.environment');

        return view('console.settings.api-keys', [
            'keys' => ApiKey::where('environment_id', $env->id)->latest()->get(),
            'newKey' => session('new_key'),
            'apiUrl' => rtrim(config('notifora.api_url'), '/'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        [$key, $plain] = ApiKey::issue(app('current.environment'), $data['name'], auth()->id());
        AuditLog::record(app('current.workspace')->id, 'apikey.created', 'api_key', $key->id, ['name' => $key->name]);
        app(SystemNotifier::class)->apiKeyCreated(app('current.workspace'), $key, app('current.environment'), $request->user());

        // Key lengkap hanya ditampilkan sekali (flash).
        return redirect()->route('console.api-keys.index')->with('new_key', $plain);
    }

    public function destroy(string $id)
    {
        $key = ApiKey::where('environment_id', app('current.environment')->id)->findOrFail($id);
        $key->update(['revoked_at' => now()]);
        Cache::forget('apikey:'.$key->key_hash);
        AuditLog::record(app('current.workspace')->id, 'apikey.revoked', 'api_key', $key->id, ['name' => $key->name]);
        app(SystemNotifier::class)->apiKeyRevoked(app('current.workspace'), $key, app('current.environment'), auth()->user());

        return back()->with('success', 'API key dicabut.');
    }
}
