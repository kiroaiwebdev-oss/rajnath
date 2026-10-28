<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ledger\Wallet;
use App\Domain\Payments\PaymentService;
use App\Domain\Payments\PaymentUnavailable;
use App\Domain\Payments\RazorpayConfig;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\RechargePackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

/** Wallet, recharge packages and payments for the Flutter app (Razorpay Android SDK). */
class WalletController extends Controller
{
    public function __construct(private Wallet $wallet, private PaymentService $payments) {}

    public function show(Request $request, RazorpayConfig $cfg): JsonResponse
    {
        $b = $this->wallet->balances($request->user());

        return response()->json(['data' => [
            'currency' => 'INR', 'cash_minor' => $b['cash'], 'bonus_minor' => $b['bonus'], 'available_minor' => $b['available'],
            'reserved_minor' => $b['reserved'], 'held_minor' => $b['held'],
            'payments_available' => $cfg->ready(), 'payments_mode' => $cfg->mode(),
        ]]);
    }

    public function packages(): JsonResponse
    {
        return response()->json(['data' => RechargePackage::where('active', true)->orderBy('sort')->orderBy('amount_minor')->get()
            ->map(fn ($p) => ['ref' => $p->ref, 'name' => $p->name, 'amount_minor' => $p->amount_minor, 'bonus_minor' => $p->bonus_minor, 'currency' => 'INR'])]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $before = $request->integer('before') ?: null;
        $rows = $this->wallet->statement($request->user()->id, 30, $before);

        return response()->json([
            'data' => $rows->map(fn ($e) => [
                'id' => $e->id, 'at' => Carbon::parse($e->created_at, 'UTC')->toIso8601ZuluString(), 'description' => $e->memo,
                'balance_type' => match ($e->kind) {
                    'customer_cash' => 'paid', 'customer_bonus' => 'bonus', default => 'held'
                },
                'direction' => $e->direction, 'amount_minor' => (int) $e->amount_minor, 'balance_after_minor' => (int) $e->balance_after_minor, 'transaction_ref' => $e->tx_ref,
            ]),
            'next_before' => $rows->count() === 30 ? $rows->last()->id : null,
        ]);
    }

    public function recharge(Request $request): JsonResponse
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if (! preg_match('/^[A-Za-z0-9_-]{8,64}$/', $key)) {
            return response()->json(['error' => ['code' => 'idempotency_key_required', 'message' => 'Send an Idempotency-Key header (8–64 letters, digits, - or _).']], 400);
        }
        $data = $request->validate(['package_ref' => ['required', 'string', 'size:26']]);
        $pk = RechargePackage::where('ref', $data['package_ref'])->where('active', true)->first();
        if (! $pk) {
            return response()->json(['error' => ['code' => 'package_unavailable', 'message' => 'This recharge option is not available.']], 422);
        }
        try {
            $p = $this->payments->startRecharge($request->user(), $pk, 'api:'.$key);
        } catch (PaymentUnavailable $e) {
            return response()->json(['error' => ['code' => 'payments_unavailable', 'message' => $e->getMessage()]], 503);
        }

        return response()->json(['data' => $this->present($p) + ['checkout' => $p->status === 'created' ? $this->payments->checkoutOptions($p, $request->user()) : null]], 201);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => Payment::where('user_id', $request->user()->id)->latest()->limit(50)->get()->map(fn ($p) => $this->present($p))]);
    }

    public function showPayment(Request $request, string $ref): JsonResponse
    {
        return response()->json(['data' => $this->present($this->find($request, $ref))]);
    }

    public function confirm(Request $request, string $ref): JsonResponse
    {
        $p = $this->find($request, $ref);
        $data = $request->validate([
            'razorpay_payment_id' => ['required', 'string', 'max:64', 'regex:/^pay_[A-Za-z0-9]+$/'],
            'razorpay_order_id' => ['required', 'string', 'max:64'],
            'razorpay_signature' => ['required', 'string', 'size:64', 'regex:/^[0-9a-f]+$/'],
        ]);
        try {
            $p = $this->payments->confirmFromCheckout($p, $data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature']);
        } catch (RuntimeException) {
            return response()->json(['error' => ['code' => 'signature_invalid', 'message' => 'Payment confirmation could not be verified.']], 422);
        }

        return response()->json(['data' => $this->present($p)]);
    }

    public function refresh(Request $request, string $ref): JsonResponse
    {
        return response()->json(['data' => $this->present($this->payments->reconcile($this->find($request, $ref)))]);
    }

    private function find(Request $request, string $ref): Payment
    {
        return Payment::where('ref', $ref)->where('user_id', $request->user()->id)->firstOrFail();
    }

    private function present(Payment $p): array
    {
        return [
            'ref' => $p->ref, 'purpose' => $p->purpose, 'description' => $p->snapshot['name'] ?? $p->label(),
            'amount_minor' => $p->amount_minor, 'bonus_minor' => $p->bonus_minor, 'refunded_minor' => $p->refunded_minor, 'currency' => $p->currency,
            'status' => $p->status, 'status_label' => Payment::STATUSES[$p->status] ?? $p->status, 'status_reason' => $p->status_reason,
            'mode' => $p->provider_mode, 'method' => $p->method,
            'created_at' => $p->created_at->toIso8601ZuluString(), 'captured_at' => $p->captured_at?->toIso8601ZuluString(),
        ];
    }
}
