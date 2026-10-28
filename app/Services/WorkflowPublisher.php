<?php

namespace App\Services;

use App\Enums\ChannelType;
use App\Enums\StepType;
use App\Models\Workflow;
use App\Models\WorkflowVersion;

class WorkflowPublisher
{
    public function __construct(private TemplateCompiler $templates) {}

    /** @return string[] */
    public function validate(Workflow $workflow): array
    {
        $steps = $workflow->steps()->with('template')->get();
        $errors = [];
        $max = (int) config('notifora.limits.max_steps');

        if ($steps->isEmpty()) {
            $errors[] = 'Workflow harus memiliki minimal 1 step.';
        }
        if ($steps->count() > $max) {
            $errors[] = "Maksimal $max step.";
        }
        if ($steps->isNotEmpty() && $steps->last()->type === StepType::FILTER) {
            $errors[] = 'Step FILTER tidak boleh menjadi step terakhir.';
        }
        if ($steps->isNotEmpty() && $steps->where('type', StepType::CHANNEL)->isEmpty()) {
            $errors[] = 'Workflow harus memiliki minimal 1 step CHANNEL.';
        }

        foreach ($steps as $s) {
            $label = "Step #{$s->step_order} ({$s->name})";
            if ($s->type === StepType::CHANNEL) {
                if (! $s->channel) {
                    $errors[] = "$label: channel belum dipilih.";
                }
                if (! $s->template || trim($s->template->body) === '') {
                    $errors[] = "$label: isi pesan (body) wajib diisi.";
                } else {
                    foreach ([$s->template->subject, $s->template->body, $s->template->body_html] as $text) {
                        foreach ($this->templates->validate((string) $text) as $e) {
                            $errors[] = "$label: $e";
                        }
                    }
                }
                if ($s->channel === ChannelType::EMAIL && (! $s->template || trim((string) $s->template->subject) === '')) {
                    $errors[] = "$label: subject email wajib diisi.";
                }
                if ($s->fallback_step_id) {
                    $fb = $steps->firstWhere('id', $s->fallback_step_id);
                    if (! $fb || $fb->type !== StepType::CHANNEL || $fb->id === $s->id) {
                        $errors[] = "$label: fallback tidak valid.";
                    }
                }
            } elseif ($s->type === StepType::DELAY) {
                $sec = (int) ($s->config['seconds'] ?? 0);
                if ($sec < 1 || $sec > config('notifora.limits.max_delay_seconds')) {
                    $errors[] = "$label: delay harus 1 detik sampai 30 hari.";
                }
            } elseif ($s->type === StepType::FILTER) {
                $rules = $s->config['rules'] ?? null;
                if (! is_array($rules) || $rules === []) {
                    $errors[] = "$label: filter belum punya rule.";
                } else {
                    foreach (array_values($rules) as $i => $rule) {
                        $op = is_array($rule) ? ($rule['op'] ?? 'eq') : null;
                        if (! is_array($rule) || ! is_string($rule['path'] ?? null)
                            || ! in_array($op, ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'contains', 'exists'], true)) {
                            $errors[] = "$label: rule #".($i + 1).' tidak valid.';
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /** Buat snapshot immutable dan aktifkan workflow. */
    public function publish(Workflow $workflow, ?int $userId = null): WorkflowVersion
    {
        $steps = $workflow->steps()->with('template')->get();

        $snapshot = [
            'is_critical' => $workflow->is_critical,
            'priority' => $workflow->priority,
            'steps' => $steps->map(fn ($s) => [
                'id' => $s->id,
                'order' => $s->step_order,
                'type' => $s->type->value,
                'name' => $s->name,
                'channel' => $s->channel?->value,
                'provider_id' => $s->channel_provider_id,
                'config' => $s->config ?? [],
                'fallback_step_id' => $s->fallback_step_id,
                'template' => $s->template ? [
                    'subject' => $s->template->subject,
                    'body' => $s->template->body,
                    'body_html' => $s->template->body_html,
                ] : null,
            ])->values()->all(),
        ];

        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version' => ((int) $workflow->versions()->max('version')) + 1,
            'snapshot' => $snapshot,
            'published_by' => $userId,
            'published_at' => now(),
        ]);

        $workflow->update(['published_version_id' => $version->id, 'status' => 'ACTIVE']);

        return $version;
    }
}
