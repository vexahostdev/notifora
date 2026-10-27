<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Plan;
use App\Models\Workspace;
use App\Services\AdminAudit;
use App\Services\PlanCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    private const BUILTIN = ['starter', 'pro', 'business', 'scale'];

    public function index()
    {
        $subs = Workspace::selectRaw('plan, count(*) c')->groupBy('plan')->pluck('c', 'plan');

        return view('admin.plans.index', [
            'plans' => PlanCatalog::all(),
            'free' => config('notifora.free_plan'),
            'subs' => $subs,
            'builtin' => self::BUILTIN,
            'overridden' => Plan::pluck('key')->all(),
        ]);
    }

    public function update(Request $request, string $key)
    {
        abort_unless($key === PlanCatalog::FREE || isset(PlanCatalog::all()[$key]), 404);
        $this->save($request, $key);

        return back()->with('success', "Plan {$key} disimpan. Berlaku langsung untuk semua workspace.");
    }

    public function store(Request $request)
    {
        $request->validate(['key' => ['required', 'regex:/^[a-z][a-z0-9_]{1,19}$/', Rule::notIn(array_merge([PlanCatalog::FREE], array_keys(PlanCatalog::all())))]], ['key.regex' => 'Key: huruf kecil/angka/_, diawali huruf, maks. 20 karakter.', 'key.not_in' => 'Key plan sudah dipakai.']);
        $this->save($request, $request->input('key'));

        return back()->with('success', 'Plan baru dibuat.');
    }

    public function destroy(string $key)
    {
        abort_if(in_array($key, array_merge(self::BUILTIN, [PlanCatalog::FREE]), true), 403, 'Plan bawaan tidak dapat dihapus (nonaktifkan saja).');
        if (Workspace::where('plan', $key)->exists()) {
            return back()->with('error', 'Plan masih dipakai workspace. Nonaktifkan saja atau pindahkan workspace terlebih dahulu.');
        }
        Plan::where('key', $key)->delete();
        PlanCatalog::forget();
        AdminAudit::record('plan.deleted', 'plan', $key);

        return back()->with('success', 'Plan dihapus.');
    }

    private function save(Request $request, string $key): void
    {
        $d = $request->validate([
            'label' => ['required', 'string', 'max:40'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'events' => ['required', 'integer', 'min:1'],
            'rpm' => ['required', 'integer', 'min:1'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'active' => ['nullable', 'boolean'],
        ]);
        $before = Plan::find($key)?->only(['label', 'price', 'events', 'rpm', 'retention_days', 'active']);
        $isFree = $key === PlanCatalog::FREE;

        Plan::updateOrCreate(['key' => $key], [
            'label' => $d['label'],
            'price' => $isFree ? 0 : (int) $d['price'],
            'events' => (int) $d['events'],
            'rpm' => (int) $d['rpm'],
            'retention_days' => (int) $d['retention_days'],
            'sort' => (int) ($d['sort'] ?? 0),
            'active' => $isFree ? true : $request->boolean('active'),
        ]);
        PlanCatalog::forget();
        PlanCatalog::apply();
        AdminAudit::record('plan.saved', 'plan', $key, null, ['before' => $before, 'after' => $d]);
    }

    // --- Kupon ----------------------------------------------------------------

    public function coupons()
    {
        return view('admin.plans.coupons', [
            'coupons' => Coupon::withCount('redemptions')->latest()->get(),
            'plans' => PlanCatalog::all(),
        ]);
    }

    public function couponStore(Request $request)
    {
        $d = $this->couponData($request);
        $d['code'] = strtoupper(preg_replace('/\s+/', '', $request->validate(['code' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/', 'unique:coupons,code']])['code']));
        $coupon = Coupon::create($d);
        AdminAudit::record('coupon.created', 'coupon', $coupon->id, null, ['code' => $coupon->code, 'type' => $coupon->type, 'value' => $coupon->value]);

        return back()->with('success', "Kupon {$coupon->code} dibuat.");
    }

    public function couponUpdate(Request $request, Coupon $coupon)
    {
        if ($request->has('toggle')) {
            $coupon->update(['active' => ! $coupon->active]);
        } else {
            $coupon->update($this->couponData($request));
        }
        AdminAudit::record('coupon.updated', 'coupon', $coupon->id, null, ['code' => $coupon->code, 'active' => $coupon->active]);

        return back()->with('success', 'Kupon diperbarui.');
    }

    public function couponDestroy(Coupon $coupon)
    {
        if ($coupon->redemptions()->exists()) {
            $coupon->update(['active' => false]);
            AdminAudit::record('coupon.deactivated', 'coupon', $coupon->id, null, ['code' => $coupon->code]);

            return back()->with('success', 'Kupon sudah pernah dipakai, jadi hanya dinonaktifkan (riwayat dipertahankan).');
        }
        AdminAudit::record('coupon.deleted', 'coupon', $coupon->id, null, ['code' => $coupon->code]);
        $coupon->delete();

        return back()->with('success', 'Kupon dihapus.');
    }

    private function couponData(Request $request): array
    {
        $d = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'per_workspace_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'min_months' => ['required', 'integer', 'min:1', 'max:12'],
            'expires_at' => ['nullable', 'date'],
            'plans' => ['nullable', 'array'],
            'plans.*' => [Rule::in(array_keys(PlanCatalog::all()))],
        ]);
        if ($d['type'] === 'percent' && $d['value'] > 100) {
            throw ValidationException::withMessages(['value' => 'Persentase maksimal 100.']);
        }

        return [
            'description' => $d['description'] ?? null,
            'type' => $d['type'],
            'value' => (int) $d['value'],
            'max_redemptions' => $d['max_redemptions'] ?? null,
            'per_workspace_limit' => (int) $d['per_workspace_limit'],
            'min_months' => (int) $d['min_months'],
            'expires_at' => $d['expires_at'] ?? null,
            'plans' => ! empty($d['plans']) ? array_values($d['plans']) : null,
            'active' => $request->boolean('active', true),
        ];
    }
}
