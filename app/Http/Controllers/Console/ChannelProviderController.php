<?php

namespace App\Http\Controllers\Console;

use App\Enums\ChannelType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ChannelProvider;
use App\Services\Providers\ProviderFactory;
use App\Services\Providers\SendRequest;
use Illuminate\Http\Request;

class ChannelProviderController extends Controller
{
    public function index()
    {
        $providers = ChannelProvider::get()->keyBy(fn ($p) => $p->channel->value);

        return view('console.channels.index', ['channels' => ChannelType::cases(), 'providers' => $providers]);
    }

    public function edit(string $channel)
    {
        $type = ChannelType::from($channel);
        abort_if($type === ChannelType::IN_APP, 404);
        $provider = ChannelProvider::where('channel', $type->value)->first();

        return view('console.channels.edit', [
            'channel' => $type,
            'provider' => $provider,
            'fields' => ChannelProvider::schema(ChannelProvider::providerFor($type)),
            'webhookUrl' => $provider ? rtrim(config('notifora.api_url'), '/').'/v1/hooks/'.$provider->provider.'/'.$provider->webhook_token : null,
        ]);
    }

    public function update(Request $request, string $channel)
    {
        $type = ChannelType::from($channel);
        abort_if($type === ChannelType::IN_APP, 404);
        $fields = ChannelProvider::schema(ChannelProvider::providerFor($type));

        $rules = ['name' => ['required', 'string', 'max:100'], 'status' => ['required', 'in:active,disabled']];
        foreach ($fields as $key => $label) {
            $rules[$key] = ['nullable', 'string', 'max:500'];
        }
        $data = $request->validate($rules);

        $provider = ChannelProvider::firstOrNew(['channel' => $type->value]);
        $creds = $provider->credentials ?? [];
        foreach ($fields as $key => $label) {
            // Kosong = pertahankan nilai lama (kredensial tidak pernah ditampilkan ulang)
            if (isset($data[$key]) && $data[$key] !== '') {
                $creds[$key] = $data[$key];
            }
        }
        foreach (array_keys($fields) as $key) {
            if (empty($creds[$key]) && ! in_array($key, ['from_name'], true)) {
                return back()->withInput()->withErrors([$key => 'Field ini wajib diisi.']);
            }
        }
        if (isset($creds['endpoint_url']) && ! preg_match('#^https?://#i', $creds['endpoint_url'])) {
            return back()->withInput()->withErrors(['endpoint_url' => 'URL harus diawali http:// atau https://']);
        }

        $provider->fill([
            'provider' => ChannelProvider::providerFor($type),
            'name' => $data['name'],
            'status' => $data['status'],
            'credentials' => $creds,
            'is_default' => true,
        ])->save();

        AuditLog::record(app('current.workspace')->id, 'provider.saved', 'provider', $provider->id, ['channel' => $type->value]);

        return redirect()->route('console.channels.edit', $channel)->with('success', 'Provider disimpan.');
    }

    /** Test connection: kirim pesan uji nyata ke tujuan yang diisi pengguna. */
    public function test(Request $request, string $channel, ProviderFactory $factory)
    {
        $type = ChannelType::from($channel);
        $provider = ChannelProvider::where('channel', $type->value)->firstOrFail();
        $data = $request->validate(['to' => ['required', 'string', 'max:191']]);

        $result = $factory->make($provider)->send(new SendRequest(
            environmentId: app('current.environment')->id,
            to: $data['to'],
            subject: 'Tes koneksi Notifora',
            content: 'Ini pesan uji dari Notifora. Koneksi provider berfungsi.',
        ));
        $provider->update(['last_tested_at' => now()]);

        return $result->success
            ? back()->with('success', 'Pesan uji terkirim (id: '.($result->messageId ?? '-').').')
            : back()->with('error', 'Gagal: '.($result->errorCode ?? 'error').' — '.mb_substr((string) $result->error, 0, 200));
    }

    public function destroy(string $channel)
    {
        ChannelProvider::where('channel', ChannelType::from($channel)->value)->delete();

        return redirect()->route('console.channels.index')->with('success', 'Provider dihapus.');
    }
}
