<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Requests\Payment\SubmitPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    /**
     * Submit payment method and optional proof of payment for an order.
     */
    public function submit(SubmitPaymentRequest $request, Order $order): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->role !== UserRole::Admin && $order->buyer_id !== $user->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke pesanan ini.',
            ], 403);
        }

        if ($order->order_status === OrderStatus::Cancelled) {
            return response()->json([
                'message' => 'Pesanan telah dibatalkan, pembayaran tidak dapat dilakukan.',
            ], 422);
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            return response()->json([
                'message' => 'Pesanan ini sudah lunas.',
            ], 422);
        }

        $paymentProofUrl = $order->payment?->payment_proof_url;

        if ($request->hasFile('payment_proof')) {
            $path = $request->file('payment_proof')->store('payments', 'public');
            $paymentProofUrl = Storage::disk('public')->url($path);
        }

        $paymentMethod = PaymentMethod::from($request->validated('payment_method'));

        $payment = DB::transaction(function () use ($order, $paymentMethod, $paymentProofUrl, $request): Payment {
            $payment = Payment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'payment_method' => $paymentMethod,
                    'amount_paid' => $order->total_amount,
                    'payment_proof_url' => $paymentProofUrl,
                    'external_reference_id' => $request->validated('external_reference_id'),
                    'paid_at' => $paymentMethod === PaymentMethod::Cod ? null : now(),
                ]
            );

            // For Cash on Delivery, order is immediately confirmed for pickup/delivery
            if ($paymentMethod === PaymentMethod::Cod && $order->order_status === OrderStatus::Pending) {
                $order->update([
                    'order_status' => OrderStatus::Confirmed,
                ]);
            }

            return $payment;
        });

        $payment->load(['verifier', 'order']);

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Retrieve payment information for a specific order.
     */
    public function show(Order $order): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        if ($user->role !== UserRole::Admin && $order->buyer_id !== $user->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke pembayaran pesanan ini.',
            ], 403);
        }

        $payment = $order->payment;

        if (! $payment) {
            return response()->json([
                'message' => 'Belum ada data pembayaran untuk pesanan ini.',
            ], 404);
        }

        $payment->load(['verifier', 'order']);

        return (new PaymentResource($payment))->response();
    }
}
