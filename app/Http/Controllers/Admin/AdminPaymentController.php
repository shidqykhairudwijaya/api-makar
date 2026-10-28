<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\VerifyPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPaymentController extends Controller
{
    /**
     * Display a paginated listing of payments with optional verification filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()
            ->with(['order.buyer.profile', 'verifier'])
            ->latest();

        if ($request->has('verified')) {
            $isVerified = filter_var($request->query('verified'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isVerified === true) {
                $query->whereNotNull('verified_by');
            } elseif ($isVerified === false) {
                $query->whereNull('verified_by');
            }
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->query('payment_method'));
        }

        $perPage = $request->integer('per_page', 15);
        $payments = $query->paginate($perPage);

        return PaymentResource::collection($payments)->response();
    }

    /**
     * Verify or reject a customer's submitted payment proof.
     */
    public function verify(VerifyPaymentRequest $request, Payment $payment): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $action = $request->validated('action');

        $message = DB::transaction(function () use ($payment, $admin, $action, $request): string {
            $order = $payment->order;

            if ($action === 'approve') {
                $payment->update([
                    'verified_by' => $admin->id,
                    'paid_at' => now(),
                ]);

                $order->update([
                    'payment_status' => PaymentStatus::Paid,
                    'order_status' => OrderStatus::Confirmed,
                ]);

                return 'Pembayaran berhasil diverifikasi dan disetujui.';
            }

            // Reject action
            $reason = $request->validated('rejection_reason');

            $payment->update([
                'verified_by' => null,
            ]);

            $order->update([
                'payment_status' => PaymentStatus::Unpaid,
                'notes' => "Verifikasi pembayaran ditolak: {$reason}",
            ]);

            return 'Pembayaran telah ditolak.';
        });

        $payment->load(['verifier', 'order.buyer.profile']);

        return response()->json([
            'message' => $message,
            'data' => new PaymentResource($payment),
        ]);
    }
}
