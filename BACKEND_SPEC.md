# NOTIFORA — Backend Technical Specification
**Version:** 2.1.0 (Development-Ready)
**Stack:** PHP 8.2+, Laravel 12, MySQL 8.0 (InnoDB, strict), Redis 7.2, Laravel Horizon, Node.js 20 (socket-engine)
**Prioritas dokumen:** [PRD](PRD_NOTIFORA_FULL.md) → dokumen ini → [FRONTEND_SPEC](FRONTEND_SPEC.md)

---

## DAFTAR ISI
1. [Arsitektur & Pemisahan Service](#1-arsitektur--pemisahan-service)
2. [Struktur Folder & Konvensi](#2-struktur-folder--konvensi)
3. [Database Schema](#3-database-schema)
4. [Queue & Jobs](#4-queue--jobs)
5. [Provider Abstraction Layer](#5-provider-abstraction-layer)
6. [Workflow Engine](#6-workflow-engine)
7. [Template Engine](#7-template-engine)
8. [API Ingress: Auth, Rate Limit, Idempotency](#8-api-ingress)
9. [Socket Engine](#9-socket-engine)
10. [Outbound Webhook Dispatcher](#10-outbound-webhook-dispatcher)
11. [Environment Variables](#11-environment-variables)
12. [Testing Strategy](#12-testing-strategy)
13. [Urutan Implementasi (Build Order)](#13-urutan-implementasi)

---

## 1. ARSITEKTUR & PEMISAHAN SERVICE

1. **Laravel App (:8040)** — HTTP API `/v1/*`, console (Blade), auth, validasi, persist event, dispatch job. **Tidak memanggil provider secara sinkron.**
2. **Worker (Horizon/Supervisor)** — workflow engine, pengiriman channel, webhook outbound, agregasi usage.
3. **Socket Engine (Node :8042)** — koneksi WebSocket inbox; verifikasi JWT; relay dari Redis pub/sub.
4. **MySQL** — sumber kebenaran. **Redis** — queue, cache, rate limit, idempotency lock, pub/sub.

Redis DB: `0` cache · `1` queue · `2` rate limit & idempotency · pub/sub memakai koneksi terpisah.

## 2. STRUKTUR FOLDER & KONVENSI

Lihat struktur lengkap di PRD §3. Konvensi:
- PK `uuid` (`HasUuids`) untuk tabel konfigurasi; `ulid` untuk `notification_events`, `message_logs`, `delivery_attempts`, `in_app_messages`, `webhook_deliveries` (sortable by time).
- Semua model bertenant memakai trait `BelongsToEnvironment` (global scope `EnvironmentScope` + auto-isi `environment_id` dari `app('current.environment')`). Job membawa `environment_id` dan memanggil `Environment::run($id, fn)` agar scope aktif di worker.
- Enum PHP 8.1 backed-string di `app/Enums`, dipakai juga sebagai cast model.
- Response API lewat `App\Support\ApiResponse` (lihat PRD §8.1). Controller tipis; logika di Services.
- Waktu disimpan UTC; `timezone` subscriber hanya dipakai saat render.

### Enums
```php
enum ChannelType: string { case WHATSAPP='WHATSAPP'; case EMAIL='EMAIL'; case TELEGRAM='TELEGRAM'; case IN_APP='IN_APP'; }
enum StepType: string { case CHANNEL='CHANNEL'; case DELAY='DELAY'; case FILTER='FILTER'; }
enum ExecutionStatus: string { case PENDING='PENDING'; case PROCESSING='PROCESSING'; case COMPLETED='COMPLETED'; case PARTIAL='PARTIAL'; case FAILED='FAILED'; case CANCELED='CANCELED'; }
enum StepStatus: string { case PENDING='PENDING'; case QUEUED='QUEUED'; case PROCESSING='PROCESSING'; case WAITING='WAITING'; case COMPLETED='COMPLETED'; case FAILED='FAILED'; case SKIPPED='SKIPPED'; }
enum MessageStatus: string { case QUEUED='QUEUED'; case SENT='SENT'; case DELIVERED='DELIVERED'; case READ='READ'; case FAILED='FAILED';
    public function rank(): int { return match($this){ self::QUEUED=>0, self::SENT=>1, self::DELIVERED=>2, self::READ=>3, self::FAILED=>-1 }; } }
enum MemberRole: string { case OWNER='OWNER'; case ADMIN='ADMIN'; case DEVELOPER='DEVELOPER'; case VIEWER='VIEWER'; }
enum EnvironmentType: string { case DEVELOPMENT='DEVELOPMENT'; case PRODUCTION='PRODUCTION'; }
```

---

## 3. DATABASE SCHEMA

Semua tabel: InnoDB, `utf8mb4_unicode_ci`, FK dengan `cascadeOnDelete()` kecuali dinyatakan lain. `*` = unique. Timestamp `created_at/updated_at` ada di semua tabel kecuali dicatat.

### 3.1 Identitas & tenant
| Tabel | Kolom penting |
|---|---|
| `users` | id uuid, name, email*, password, two_factor_secret (encrypted, null), two_factor_recovery_codes (encrypted json), two_factor_confirmed_at, email_verified_at, remember_token |
| `workspaces` | id, name, slug*, plan (`starter|pro|business|scale`, default `starter`), quota_events_monthly int, log_retention_days int, billing_email, owner_id → users (restrict) |
| `members` | id, workspace_id, user_id, role (MemberRole); unique(workspace_id,user_id) |
| `invitations` | id, workspace_id, email, role, token_hash*, expires_at, accepted_at |
| `environments` | id, workspace_id, type (EnvironmentType), name, color; unique(workspace_id,type) |
| `api_keys` | id, environment_id, name, key_prefix(12), key_hash char(64)*, last4(4), scopes json (default `["*"]`), last_used_at, revoked_at, created_by → users |
| `audit_logs` | id ulid, workspace_id, user_id null, action, target_type, target_id, meta json, ip, created_at (tanpa updated_at); index(workspace_id,created_at) |

### 3.2 Konfigurasi
| Tabel | Kolom penting |
|---|---|
| `channel_providers` | id, environment_id, channel (ChannelType), provider (`vexahost_wa|postivo|telegram|internal`), name, credentials text (cast `encrypted:array`), is_default bool, status (`active|degraded|disabled`), webhook_token char(40)*, last_tested_at; index(environment_id,channel,is_default) |
| `workflows` | id, environment_id, name, trigger_key varchar(100), status (`DRAFT|ACTIVE|ARCHIVED`), priority (`HIGH|NORMAL`), is_critical bool (abaikan opt-out per-workflow), published_version_id null; unique(environment_id,trigger_key) |
| `workflow_versions` | id, workflow_id, version int, snapshot json (steps+templates), published_by, published_at; unique(workflow_id,version) |
| `workflow_steps` | id, workflow_id, step_order smallint, type (StepType), name, channel (ChannelType null), channel_provider_id null (null = default), config json (delay: `{seconds}`; filter: `{rules:[…]}`), fallback_step_id null → workflow_steps (nullOnDelete); unique(workflow_id,step_order) |
| `step_templates` | id, workflow_step_id*, subject text null, body mediumtext, body_html mediumtext null, locale varchar(10) default `id`; unique(workflow_step_id,locale) |
| `webhook_subscriptions` | id, environment_id, url varchar(500), secret text (encrypted), events json, status (`active|disabled`), consecutive_failures int, disabled_at |

`workflow_steps` pada workflow ACTIVE di-edit hanya lewat draft baru; `publish` menulis `workflow_versions.snapshot`.

### 3.3 Subscriber
| Tabel | Kolom |
|---|---|
| `subscribers` | id, environment_id, external_id varchar(191), email null, phone null (E.164), telegram_chat_id null, first_name, last_name, locale default `id`, timezone default `Asia/Jakarta`, data json; unique(environment_id,external_id); index(environment_id,email), index(environment_id,phone); soft deletes tidak dipakai (erasure = hard delete + anonymize logs) |
| `subscriber_preferences` | id, subscriber_id, workflow_id null (null=global), channel (ChannelType), enabled bool; unique(subscriber_id,workflow_id,channel) |

(Tabel `subscriber_channels` pada dokumen lama **dihapus**: destination disimpan langsung di kolom `subscribers`; satu destination per channel cukup untuk MVP.)

### 3.4 Eksekusi (volume tinggi)
```php
Schema::create('notification_events', function (Blueprint $t) {
    $t->ulid('id')->primary();
    $t->foreignUuid('environment_id')->constrained()->cascadeOnDelete();
    $t->foreignUuid('workflow_id')->constrained()->cascadeOnDelete();
    $t->foreignUuid('workflow_version_id')->constrained('workflow_versions');
    $t->foreignUuid('subscriber_id')->constrained()->cascadeOnDelete();
    $t->string('idempotency_key', 191)->nullable();
    $t->char('request_hash', 64)->nullable();      // sha256 body, untuk deteksi idempotency_conflict
    $t->json('payload');
    $t->json('overrides')->nullable();
    $t->string('source', 20)->default('api');       // api|console_test|bulk
    $t->boolean('is_test')->default(false);
    $t->timestamps();
    $t->unique(['environment_id', 'idempotency_key']);
    $t->index(['environment_id', 'created_at']);
    $t->index(['subscriber_id', 'created_at']);
});

Schema::create('workflow_executions', function (Blueprint $t) {
    $t->ulid('id')->primary();
    $t->foreignUlid('notification_event_id')->unique()->constrained('notification_events')->cascadeOnDelete();
    $t->foreignUuid('environment_id')->constrained()->cascadeOnDelete();
    $t->string('status', 20)->default('PENDING');
    $t->timestamp('started_at')->nullable();
    $t->timestamp('finished_at')->nullable();
    $t->timestamps();
    $t->index(['environment_id', 'status', 'created_at']);
});

Schema::create('step_executions', function (Blueprint $t) {
    $t->ulid('id')->primary();
    $t->foreignUlid('workflow_execution_id')->constrained('workflow_executions')->cascadeOnDelete();
    $t->foreignUuid('workflow_step_id')->constrained('workflow_steps')->cascadeOnDelete();
    $t->unsignedSmallInteger('step_order');
    $t->string('status', 20)->default('PENDING');
    $t->string('skip_reason', 40)->nullable();      // opted_out|filter_false|no_destination|canceled
    $t->boolean('is_fallback')->default(false);
    $t->string('rendered_subject', 500)->nullable();
    $t->mediumText('rendered_content')->nullable();
    $t->timestamp('run_at')->nullable();            // untuk step DELAY
    $t->timestamp('executed_at')->nullable();
    $t->timestamps();
    $t->index(['workflow_execution_id', 'step_order']);
    $t->index(['status', 'run_at']);
});

Schema::create('message_logs', function (Blueprint $t) {
    $t->ulid('id')->primary();
    $t->foreignUlid('step_execution_id')->unique()->constrained('step_executions')->cascadeOnDelete(); // 1 log per step (bukan per retry)
    $t->foreignUuid('environment_id')->constrained()->cascadeOnDelete();
    $t->foreignUuid('subscriber_id')->nullable()->constrained()->nullOnDelete(); // null setelah erasure
    $t->foreignUuid('workflow_id')->nullable()->constrained()->nullOnDelete();
    $t->string('channel_type', 30);
    $t->string('provider', 40)->nullable();
    $t->string('destination_masked', 191);          // "+62812****890" — nilai asli tidak disimpan
    $t->string('status', 20)->default('QUEUED');
    $t->string('external_message_id', 191)->nullable()->index();
    $t->string('error_code', 60)->nullable();
    $t->text('error_message')->nullable();
    $t->unsignedTinyInteger('attempts')->default(0);
    $t->timestamp('sent_at')->nullable();
    $t->timestamp('delivered_at')->nullable();
    $t->timestamp('read_at')->nullable();
    $t->timestamp('failed_at')->nullable();
    $t->timestamps();
    $t->index(['environment_id', 'status', 'created_at']);
    $t->index(['environment_id', 'channel_type', 'created_at']);
    $t->index(['subscriber_id', 'created_at']);
});

Schema::create('delivery_attempts', function (Blueprint $t) {
    $t->ulid('id')->primary();
    $t->foreignUlid('message_log_id')->constrained('message_logs')->cascadeOnDelete();
    $t->unsignedTinyInteger('attempt');
    $t->boolean('success');
    $t->boolean('retryable')->default(false);
    $t->unsignedSmallInteger('http_status')->nullable();
    $t->unsignedInteger('duration_ms')->nullable();
    $t->json('raw_trace')->nullable();              // sudah di-redact
    $t->timestamp('created_at')->useCurrent();
});

Schema::create('in_app_messages', function (Blueprint $t) {
    $t->ulid('id')->primary();
    $t->foreignUuid('environment_id')->constrained()->cascadeOnDelete();
    $t->foreignUuid('subscriber_id')->constrained()->cascadeOnDelete();
    $t->foreignUlid('message_log_id')->nullable()->constrained('message_logs')->nullOnDelete();
    $t->string('title', 200);
    $t->text('body');
    $t->string('action_url', 500)->nullable();
    $t->json('data')->nullable();
    $t->timestamp('read_at')->nullable();
    $t->timestamp('archived_at')->nullable();
    $t->timestamps();
    $t->index(['subscriber_id', 'read_at', 'created_at']);
});
```

### 3.5 Webhook, usage
| Tabel | Kolom |
|---|---|
| `webhook_deliveries` | id ulid, webhook_subscription_id, event_type, payload json, status (`pending|success|failed`), attempt tinyint, http_status null, response_snippet(500) null, next_retry_at null, created_at |
| `usage_counters` | id, environment_id, date, events_received int, messages_sent int, messages_failed int; unique(environment_id,date) |

Tabel `failed_jobs`, `jobs` (tidak dipakai, Redis), `job_batches`, `cache`, `sessions` (driver `redis`/`database` dipilih: `database`), `password_reset_tokens` memakai migrasi bawaan Laravel.

### 3.6 Retensi
Job harian `PruneOldLogsJob`: hapus `message_logs`, `delivery_attempts`, `step_executions`, `workflow_executions`, `notification_events` yang lebih tua dari `workspaces.log_retention_days` (chunked 5.000 baris, per environment). `usage_counters` dipertahankan 24 bulan.

---

## 4. QUEUE & JOBS

### 4.1 Antrean
| Queue | Isi | Worker |
|---|---|---|
| `notifora_high` | `ProcessWorkflowEventJob` untuk workflow `HIGH`/`is_critical` (OTP) | 2 |
| `notifora_default` | `ProcessWorkflowEventJob` normal, `ResumeAfterDelayJob`, `AggregateUsageJob`, `PruneOldLogsJob` | 2 |
| `notifora_channels` | `DispatchChannelStepJob` | 6 |
| `notifora_webhooks` | `DeliverCustomerWebhookJob` | 2 |

Gagal permanen → tabel `failed_jobs` (bukan queue). Worker dijalankan Supervisor sebagai `queue:work` per pool (`docker/supervisor/worker.conf`); Horizon ditunda (butuh ext-pcntl/posix).

### 4.2 `ProcessWorkflowEventJob`
`tries=3`, `backoff=[2,10,30]`. Memuat event + versi workflow, membuat `workflow_executions` (idempotent via unique `notification_event_id`), lalu memanggil `WorkflowEngine::run($execution)`.

### 4.3 `DispatchChannelStepJob` (versi benar)
```php
class DispatchChannelStepJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 15, 60];
    public int $uniqueFor = 300;

    public function __construct(public string $stepExecutionId, public string $environmentId)
    {
        $this->onQueue('notifora_channels');
    }

    public function uniqueId(): string { return $this->stepExecutionId; }

    public function handle(ProviderFactory $factory, WorkflowEngine $engine): void
    {
        Environment::run($this->environmentId, function () use ($factory, $engine) {
            $step = StepExecution::with(['workflowStep', 'workflowExecution.event.subscriber'])->findOrFail($this->stepExecutionId);

            if (in_array($step->status, [StepStatus::COMPLETED, StepStatus::SKIPPED, StepStatus::FAILED], true)) {
                return; // idempotent terhadap redelivery
            }
            if ($step->workflowExecution->status === ExecutionStatus::CANCELED) {
                $step->update(['status' => StepStatus::SKIPPED, 'skip_reason' => 'canceled']);
                return;
            }

            $log = MessageLog::firstOrCreate(
                ['step_execution_id' => $step->id],
                [/* environment_id, subscriber_id, workflow_id, channel_type, destination_masked, status=QUEUED */]
            );
            $step->update(['status' => StepStatus::PROCESSING]);

            $provider = $factory->forStep($step->workflowStep);
            $started = hrtime(true);
            $result = $provider->send(SendRequest::fromStep($step));
            $ms = (int) ((hrtime(true) - $started) / 1e6);

            DB::transaction(function () use ($log, $step, $result, $ms) {
                $log->increment('attempts');
                DeliveryAttempt::create([/* message_log_id, attempt=$log->attempts, success, retryable, http_status, duration_ms, raw_trace=$result->redactedTrace() */]);
                if ($result->success) {
                    $log->update(['status' => MessageStatus::SENT, 'sent_at' => now(), 'external_message_id' => $result->messageId, 'error_code' => null]);
                    $step->update(['status' => StepStatus::COMPLETED, 'executed_at' => now()]);
                }
            });

            if ($result->success) { $engine->advance($step); return; }

            if ($result->retryable && $this->attempts() < $this->tries) {
                throw new RetryableProviderException($result->error); // memicu backoff
            }
            $this->markFailed($step, $log, $result);
            $engine->onStepFailed($step); // fallback atau lanjut
        });
    }

    public function failed(\Throwable $e): void { /* tandai FAILED bila belum, panggil engine->onStepFailed */ }
}
```
Aturan: **tidak ada** `MessageLog::create` per retry (unique `step_execution_id`); `destination` mentah diambil dari subscriber saat kirim, hanya versi masked yang disimpan.

### 4.4 Job lain
- `ResumeAfterDelayJob(stepExecutionId)` — dijadwalkan dengan `->delay($seconds)`; saat berjalan mengecek `CANCELED`, lalu `engine->advance`.
- `DeliverCustomerWebhookJob(deliveryId)` — §10.
- Usage dihitung langsung: `UsageMeter` meng-upsert `usage_counters` (increment atomik) di ingress dan job; tidak ada job agregasi terpisah di 1.0.
- `PruneOldLogsJob`, `DisableDeadWebhooksJob` — harian (scheduler di `routes/console.php`).

---

## 5. PROVIDER ABSTRACTION LAYER

```php
interface ProviderInterface {
    public function send(SendRequest $r): ProviderResult;
    public function validateCredentials(): bool;                    // dipakai Test Connection
    public function parseWebhook(Request $request): ?DeliveryUpdate; // null jika tidak didukung
}

final class SendRequest {  // to, subject, content (text), html, meta[], subscriber snapshot
}
final class ProviderResult {
    public function __construct(
        public bool $success, public bool $retryable = false, public ?string $messageId = null,
        public ?string $destination = null, public ?string $error = null, public ?string $errorCode = null,
        public ?int $httpStatus = null, public array $rawResponse = [],
    ) {}
    public function redactedTrace(): array { /* hapus header Authorization, token, nomor penuh */ }
}
```
`ProviderFactory::forStep(WorkflowStep)` → resolve `channel_providers` (explisit id, atau default untuk channel + environment), bungkus dengan `CircuitBreaker` (Redis: 5 gagal/60 dtk → `degraded` 60 dtk; job di-`release(30)` tanpa menambah attempt). Jika tidak ada provider → step `FAILED` kode `no_provider_configured` (non-retryable).

### 5.1 VexaHost WhatsApp (perbaikan dari versi lama)
```php
public function send(SendRequest $r): ProviderResult
{
    $phone = Phone::toE164Digits($r->to);                 // "62812…" tanpa '+'
    if (!$phone) return new ProviderResult(false, false, error: 'invalid_phone', errorCode: 'invalid_destination');

    try {
        $res = Http::withToken($this->c['api_key'])->acceptJson()->timeout(8)->connectTimeout(3)
            ->post(rtrim($this->c['endpoint_url'], '/').'/api/send-message', [
                'sessionId' => $this->c['session_id'], 'phoneNumber' => $phone, 'message' => $r->content,
            ]);
    } catch (ConnectionException $e) {
        return new ProviderResult(false, true, destination: $phone, error: $e->getMessage(), errorCode: 'connection_error');
    }

    if ($res->successful()) {
        return new ProviderResult(true, messageId: $res->json('messageId') ?? $res->json('id'), destination: $phone, httpStatus: $res->status(), rawResponse: $res->json() ?? []);
    }
    $retryable = $res->serverError() || in_array($res->status(), [408, 429], true);
    return new ProviderResult(false, $retryable, destination: $phone, error: $res->body(), errorCode: 'http_'.$res->status(), httpStatus: $res->status());
}
```
(`$phone` kini selalu terdefinisi di blok catch — bug versi lama diperbaiki.)

### 5.2 Postivo Email
`POST {endpoint_url}/api/v1/emails/send` JSON `{from, to, subject, text, html}` Bearer `api_key`. Jika `mode=smtp` di credentials gunakan Laravel Mailer dinamis (host/port 587/TLS). Mapping retryable sama dengan §5.1.

### 5.3 Telegram
`POST https://api.telegram.org/bot{token}/sendMessage` `{chat_id, text, parse_mode:'HTML'}`; 429 → retryable dengan menghormati `retry_after`; 403 (bot diblokir) → non-retryable `blocked_by_user`.

### 5.4 In-App
Tidak melalui HTTP: membuat `in_app_messages`, `Redis::publish('notifora:inapp-stream', json)`, hasil sukses seketika; status log langsung `DELIVERED`.

### 5.5 Webhook masuk dari provider
`POST /v1/hooks/{provider}/{token}` (tanpa API key; token = `channel_providers.webhook_token`). `parseWebhook()` → `DeliveryUpdate{externalMessageId, status, at}` → `MessageLog` dicari via `external_message_id`, status hanya naik (rank), lalu dispatch `DeliverCustomerWebhookJob`.

---

## 6. WORKFLOW ENGINE

`WorkflowEngine::run(WorkflowExecution)`:
1. Muat `snapshot` versi event; buat semua `step_executions` berstatus `PENDING` (urut `step_order`), `fallback` dibuat saat dibutuhkan (`is_fallback=true`).
2. Set execution `PROCESSING`, panggil `advance` dari step pertama.

`advance(StepExecution $done)` → cari step berikutnya `PENDING` dan jalankan `execute($next)`:
- **FILTER**: evaluasi rules (operator `eq,neq,gt,gte,lt,lte,in,contains,exists` atas path `payload.x`/`subscriber.x`; semua rule AND). False → *seluruh sisa workflow dihentikan* (execution `COMPLETED`, step sisa `SKIPPED:filter_false`).
- **DELAY**: status `WAITING`, `run_at=now+seconds`, `ResumeAfterDelayJob::dispatch->delay(…)`. Maks delay 30 hari.
- **CHANNEL**: `PreferenceResolver::allows($subscriber, $workflow, $channel)` — false → `SKIPPED:opted_out` lalu `advance`. Destination kosong → `SKIPPED:no_destination`. Lainnya: render `TemplateCompiler`, simpan `rendered_*`, status `QUEUED`, dispatch `DispatchChannelStepJob` ke `notifora_channels`.
- Setelah step terakhir: hitung status akhir execution (§PRD 5.1), catat `finished_at`, kirim webhook `execution.completed|failed`, increment usage.

`onStepFailed($step)`: jika `workflow_step.fallback_step_id` ada & belum fallback → buat/jalankan step fallback (`is_fallback=true`); lalu lanjut ke step berikutnya (kegagalan satu channel tidak menghentikan workflow).

Cancel (`DELETE /events/{id}`): execution `CANCELED`, step `PENDING/WAITING/QUEUED` → `SKIPPED:canceled`. Step yang sudah `PROCESSING` dibiarkan selesai.

Locking: `advance` dibungkus `Cache::lock("exec:{id}", 10)` agar callback paralel (retry + webhook) tidak menjalankan step ganda.

---

## 7. TEMPLATE ENGINE

`TemplateCompiler` — parser kustom, **bukan** Blade/eval.
- Token: `{{ path|filter:arg|filter2 }}`. Path whitelisted root: `payload`, `subscriber`, `event`, `workspace`. Akses array via dot (`payload.items.0.name`).
- Filter: `upper, lower, title, trim, default:"x", truncate:N, currency:"IDR", number:N, date:"d M Y H:i" (timezone subscriber), e (escape html), json`.
- Escape otomatis HTML untuk `body_html` dan subject email; tidak untuk teks WA/Telegram. Telegram HTML mode: escape `<>&`.
- Kontrol sederhana opsional (fase 1.1): `{% if payload.x %}…{% endif %}`; MVP hanya variabel+filter.
- Validasi saat save: sintaks valid, filter dikenal, panjang ≤ 20.000 char, output ≤ 100 KB. Variabel hilang → "" dan warning ke `meta`.
- Override channel per-trigger (`overrides.channels.email.subject/body`) divalidasi dengan aturan yang sama dan dirender oleh compiler yang sama.

---

## 8. API INGRESS

### 8.1 Middleware pipeline untuk `/v1/*`
`SetRequestId → ValidateApiKey → ApiRateLimit → (IdempotencyGuard hanya pada POST trigger/bulk) → controller`.

**ValidateApiKey**: ambil Bearer; `hash('sha256', $token)`; lookup Redis cache `apikey:{hash}` (TTL 5 dtk) lalu DB; tolak jika `revoked_at`. Set `current.environment`, `current.apikey`. Update `last_used_at` via `Cache::add("apikey:touch:{id}",1,60)` guard.

**ApiRateLimit**: sliding window Redis (Lua script) per `api_key.id`, batas dari plan (PRD §8.4); header `X-RateLimit-*`; 429 `rate_limited` + `Retry-After`.

### 8.2 IdempotencyGuard (atomik, perbaikan)
```php
public function handle(Request $req, Closure $next)
{
    $key = $req->header('X-Idempotency-Key');
    if (!$key) return $next($req);
    if (strlen($key) > 191) return ApiResponse::error('validation_failed', 'Idempotency key too long', 422);

    $env  = app('current.environment')->id;
    $hash = hash('sha256', $req->method().$req->path().$req->getContent());
    $rKey = "idem:{$env}:{$key}";

    // Klaim slot secara atomik: nilai awal = status "processing"
    $claimed = Redis::connection('limiter')->set($rKey, json_encode(['state'=>'processing','hash'=>$hash]), 'EX', 86400, 'NX');

    if (!$claimed) {
        $stored = json_decode(Redis::connection('limiter')->get($rKey), true);
        if (($stored['hash'] ?? null) !== $hash) return ApiResponse::error('idempotency_conflict', 'Key reused with different body', 409);
        if (($stored['state'] ?? '') === 'processing') return ApiResponse::error('idempotency_conflict', 'Request in progress', 409)->header('Retry-After', 1);
        return response()->json($stored['body'], $stored['status'])->header('Idempotent-Replay', 'true');
    }

    try {
        $response = $next($req);
    } catch (\Throwable $e) {
        Redis::connection('limiter')->del($rKey);   // lepas slot agar klien bisa retry
        throw $e;
    }

    if ($response->isSuccessful()) {
        Redis::connection('limiter')->set($rKey, json_encode(['state'=>'done','hash'=>$hash,'status'=>$response->getStatusCode(),'body'=>json_decode($response->getContent(), true)]), 'EX', 86400);
    } else {
        Redis::connection('limiter')->del($rKey);
    }
    return $response;
}
```
Lapis kedua: unique index DB `(environment_id, idempotency_key)` — bila Redis hilang, insert duplikat ditangkap dan respons event lama dikembalikan.

### 8.3 EventTriggerController (alur)
1. `TriggerEventRequest` validasi: `workflow` required slug; `to` string|array; `to.subscriberId` required bila array; `payload` array ≤ 64KB; `overrides` opsional.
2. Resolve workflow `ACTIVE` by `trigger_key` (cache 30 dtk) → 422 `workflow_inactive`/404.
3. Cek quota (`UsageMeter::assertWithinQuota`).
4. Upsert subscriber (`firstOrNew` by external_id, update field yang dikirim saja).
5. Dalam transaksi: `notification_events::create`; di luar commit (`afterCommit`) dispatch `ProcessWorkflowEventJob` ke queue `high|default`.
6. `UsageMeter::incrementEvent`. Return `202`.

Event diselesaikan **sebelum** respons (durable), sehingga tidak ada event hilang setelah 202.

### 8.4 Auth console
Session guard `web`; rute `console.*` di `routes/web.php` dengan middleware `auth, verified, SetCurrentEnvironment` (environment aktif disimpan di session, default DEVELOPMENT). Policy per resource memeriksa `members.role`. Login throttle `5,1`. 2FA TOTP (paket `pragmarx/google2fa-laravel` atau Fortify).

---

## 9. SOCKET ENGINE

`socket-engine/server.js` (perbaikan: JWT, CORS allowlist, room per-environment, unread count):
```javascript
const { Server } = require('socket.io');
const Redis = require('ioredis');
const jwt = require('jsonwebtoken');

const PORT = Number(process.env.PORT || 8042);
const ORIGINS = (process.env.SOCKET_ALLOWED_ORIGINS || '').split(',').map(s => s.trim()).filter(Boolean);
const SECRET = process.env.SOCKET_JWT_SECRET;
const LARAVEL = process.env.LARAVEL_INTERNAL_URL;          // http://notifora-app:8040
const INTERNAL = process.env.INTERNAL_API_SECRET;
if (!SECRET || !ORIGINS.length) { console.error('SOCKET_JWT_SECRET and SOCKET_ALLOWED_ORIGINS required'); process.exit(1); }

const io = new Server(PORT, { cors: { origin: ORIGINS, credentials: false }, pingInterval: 20000, pingTimeout: 20000 });
const sub = new Redis(process.env.REDIS_URL);

io.use((socket, next) => {
  try {
    const claims = jwt.verify(socket.handshake.auth?.token, SECRET, { algorithms: ['HS256'] });
    socket.data = { sub: claims.sub, env: claims.env, exp: claims.exp };
    next();
  } catch { next(new Error('unauthorized')); }
});

io.on('connection', async (socket) => {
  const room = `env:${socket.data.env}:sub:${socket.data.sub}`;
  socket.join(room);
  const ms = socket.data.exp * 1000 - Date.now();
  const timer = setTimeout(() => { socket.emit('auth:expired'); socket.disconnect(true); }, Math.max(ms, 0));
  socket.on('disconnect', () => clearTimeout(timer));
  socket.on('notification:mark_read', async (p) => { /* POST ke ${LARAVEL}/internal/inbox/read dengan header X-Internal-Secret */ });
  // kirim unread count awal via GET ${LARAVEL}/internal/inbox/unread
});

sub.subscribe('notifora:inapp-stream');
sub.on('message', (_c, raw) => {
  const m = JSON.parse(raw);                                // {env, subscriber_id, type, data}
  io.to(`env:${m.env}:sub:${m.subscriber_id}`).emit(m.type, m.data);
});
```
`INTERNAL_API_SECRET` hanya dikenali route `/internal/*` (di-bind ke jaringan Docker internal, tidak diekspos nginx publik). Health: `GET /health` (tambah via `io.engine`/http server).

## 10. OUTBOUND WEBHOOK DISPATCHER

`DeliverCustomerWebhookJob` (queue `notifora_webhooks`):
- Body: `{id, type, created_at, data:{…}}`. Header: `X-Notifora-Event`, `X-Notifora-Delivery`, `X-Notifora-Signature: t={unix},v1={hex(hmac_sha256(secret, "{t}.{body}"))}`. Toleransi verifikasi disarankan 5 menit.
- `SafeUrl` validator: skema https (http diperbolehkan hanya di `local`), resolusi DNS lalu tolak IP private (10/8, 172.16/12, 192.168/16, 127/8, 169.254/16, ::1, fc00::/7); pakai `CURLOPT_RESOLVE` agar IP ter-pin; `allow_redirects=false`; timeout 10 dtk.
- Sukses = 2xx. Gagal → `attempt++`, jadwal ulang: 1m, 5m, 30m, 2h, 6h, 24h; setelah itu `failed`. `consecutive_failures >= 50` atau 7 hari gagal → subscription `disabled` + email ke owner.
- Replay manual dari console.

---

## 11. ENVIRONMENT VARIABLES

`.env.example` (placeholder saja — **jangan pernah commit nilai asli**):
```ini
APP_NAME=Notifora
APP_ENV=production
APP_KEY=                      # php artisan key:generate
APP_DEBUG=false
APP_URL=https://notifora.my.id/console
API_URL=https://api.notifora.my.id

LOG_CHANNEL=stack
LOG_STACK=daily,stderr
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=notifora-mysql
DB_PORT=3306
DB_DATABASE=notifora_db
DB_USERNAME=notifora_user
DB_PASSWORD=                  # wajib diisi, acak ≥ 24 karakter

REDIS_CLIENT=phpredis
REDIS_HOST=notifora-redis
REDIS_PASSWORD=               # wajib diisi
REDIS_PORT=6379
REDIS_DB=0
REDIS_QUEUE_DB=1
REDIS_LIMITER_DB=2

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true

SOCKET_PUBLIC_URL=wss://ws.notifora.my.id
SOCKET_JWT_SECRET=            # ≥ 32 byte acak
SOCKET_ALLOWED_ORIGINS=https://notifora.my.id/console,https://notifora.my.id
LARAVEL_INTERNAL_URL=http://notifora-app:8040
INTERNAL_API_SECRET=          # ≥ 32 byte acak

MAIL_MAILER=smtp              # email sistem (reset password, invite) via Postivo
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@notifora.my.id
MAIL_FROM_NAME=Notifora

SENTRY_LARAVEL_DSN=
HORIZON_ENABLED=true
```
Generator secret: `php -r "echo bin2hex(random_bytes(32));"`.

## 12. TESTING STRATEGY

| Level | Alat | Wajib mencakup |
|---|---|---|
| Unit | Pest | TemplateCompiler (termasuk upaya injeksi), PreferenceResolver, Phone, filter rules, status rank, HMAC signing |
| Feature | Pest + `Http::fake`, `Queue::fake`, Redis test | Semua AC PRD §6; idempotency (replay, conflict, race via 2 request paralel); rate limit; tenant isolation (key env A tidak melihat data env B); retry/fallback; webhook masuk menaikkan status saja |
| Integrasi | Pest + MySQL/Redis nyata (CI service) | Pipeline trigger→worker→log dengan provider fake; delay + cancel |
| Socket | Jest | JWT invalid ditolak, room terisolasi antar-subscriber |
| E2E | Playwright | Register→buat workflow→trigger test→lihat log |
| Load | k6 | 500 trigger/dtk 10 menit, p95 < 50 ms |
Target coverage: ≥ 80% pada `app/Services` dan `app/Jobs`.

## 13. URUTAN IMPLEMENTASI

1. Bootstrap Laravel 12, Pint, Larastan, Pest, Docker Compose, CI.
2. Migrasi §3 + factory + `BelongsToEnvironment` + seeder demo.
3. Auth console (register otomatis membuat workspace+env, 2FA, team).
4. API key + `ValidateApiKey` + rate limit + `ApiResponse`.
5. Subscriber API + `Phone`.
6. Workflow CRUD + versioning + `TemplateCompiler`.
7. Provider PAL + 3 adapter + circuit breaker + Test Connection.
8. `EventTriggerController` + idempotency + `WorkflowEngine` + jobs.
9. Message log, webhook masuk, status rank.
10. In-App: `in_app_messages`, inbox API, token JWT, socket-engine.
11. Preferences, webhook outbound, usage meter, retention.
12. Dashboard + hardening (security review, load test, backup).
