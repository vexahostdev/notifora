<?php

namespace Database\Seeders;

use App\Enums\ChannelType;
use App\Models\ApiKey;
use App\Models\ChannelProvider;
use App\Models\Environment;
use App\Models\StepTemplate;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Services\WorkflowPublisher;
use App\Services\WorkspaceProvisioner;
use Illuminate\Database\Seeder;

/** Data demo untuk development lokal. TIDAK untuk production. */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Seeder demo dinonaktifkan di production.');

            return;
        }

        $user = User::firstOrCreate(['email' => 'demo@notifora.test'], ['name' => 'Demo Developer', 'password' => 'Password123']);
        if ($user->memberships()->exists()) {
            $this->command?->info('Demo sudah ada: demo@notifora.test / Password123');

            return;
        }

        $workspace = app(WorkspaceProvisioner::class)->create($user, 'Demo Workspace');
        $env = Environment::with('workspace')->where('workspace_id', $workspace->id)->where('type', 'DEVELOPMENT')->first();
        app()->instance('current.environment', $env);

        ChannelProvider::create([
            'channel' => ChannelType::WHATSAPP, 'provider' => 'vexahost_wa', 'name' => 'VexaHost (demo)', 'is_default' => true,
            'credentials' => ['endpoint_url' => 'http://localhost:8051', 'api_key' => 'demo-key-change-me', 'session_id' => 'demo'],
        ]);

        $wf = Workflow::create(['name' => 'Order Confirmed', 'trigger_key' => 'order-confirmed', 'status' => 'DRAFT']);
        $inApp = WorkflowStep::create(['workflow_id' => $wf->id, 'step_order' => 1, 'type' => 'CHANNEL', 'name' => 'Inbox', 'channel' => 'IN_APP']);
        StepTemplate::create(['workflow_step_id' => $inApp->id, 'subject' => 'Pesanan {{ payload.invoiceId }} dikonfirmasi', 'body' => 'Halo {{ subscriber.first_name|default:"Pelanggan" }}, total {{ payload.amount|currency:"IDR" }}.']);
        $wa = WorkflowStep::create(['workflow_id' => $wf->id, 'step_order' => 2, 'type' => 'CHANNEL', 'name' => 'WhatsApp', 'channel' => 'WHATSAPP']);
        StepTemplate::create(['workflow_step_id' => $wa->id, 'body' => 'Halo {{ subscriber.first_name }}, pesanan {{ payload.invoiceId }} sebesar {{ payload.amount|currency:"IDR" }} sudah kami terima.']);
        app(WorkflowPublisher::class)->publish($wf, $user->id);

        [, $plain] = ApiKey::issue($env, 'demo-key', $user->id);

        $this->command?->info('Login: demo@notifora.test / Password123');
        $this->command?->info("API key (DEV, hanya tampil sekali): $plain");
    }
}
