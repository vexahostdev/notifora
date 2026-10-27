<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Payment;
use App\Models\Workspace;

class CouponService
{
    public function find(string $code): ?Coupon
    {
        return Coupon::where('code', strtoupper(trim($code)))->first();
    }

    /**
     * Validasi kupon untuk sebuah pembelian dan hitung potongan.
     *
     * @return array{coupon: Coupon, discount: int}
     */
    public function evaluate(string $code, Workspace $ws, string $plan, int $months, int $subtotal): array
    {
        $fail = fn (string $m) => throw new ApiException('validation_failed', $m, 422);

        $coupon = $this->find($code);
        if (! $coupon || ! $coupon->active) {
            $fail('Kode kupon tidak ditemukan atau tidak aktif.');
        }
        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            $fail('Kupon sudah kedaluwarsa.');
        }
        if (! empty($coupon->plans) && ! in_array($plan, $coupon->plans, true)) {
            $fail('Kupon tidak berlaku untuk plan ini.');
        }
        if ($months < $coupon->min_months) {
            $fail("Kupon berlaku untuk minimal {$coupon->min_months} bulan.");
        }
        if ($coupon->max_redemptions !== null && $coupon->usedCount() >= $coupon->max_redemptions) {
            $fail('Kuota pemakaian kupon sudah habis.');
        }
        if ($coupon->usedCount($ws->id) >= $coupon->per_workspace_limit) {
            $fail('Kupon ini sudah Anda gunakan.');
        }

        $discount = $coupon->type === 'percent'
            ? (int) floor($subtotal * min(100, $coupon->value) / 100)
            : min((int) $coupon->value, $subtotal);

        return ['coupon' => $coupon, 'discount' => max(0, $discount)];
    }

    public function redeem(Coupon $coupon, Payment $payment, Workspace $ws): void
    {
        CouponRedemption::create(['coupon_id' => $coupon->id, 'workspace_id' => $ws->id, 'payment_id' => $payment->id, 'created_at' => now()]);
    }
}
