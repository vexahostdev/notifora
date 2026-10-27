<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use Illuminate\Http\Request;

class EnvironmentController extends Controller
{
    public function switch(Request $request)
    {
        $data = $request->validate(['environment_id' => ['required', 'string']]);
        $env = Environment::where('workspace_id', app('current.workspace')->id)->findOrFail($data['environment_id']);
        session(['environment_id' => $env->id]);

        return redirect()->route('console.dashboard')->with('success', 'Environment aktif: '.$env->name);
    }
}
