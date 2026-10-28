<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminCancelOrderRequest;
use App\Http\Requests\Admin\AssignCourierRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
{
    /**
     * Display a comprehensive paginated listing of all marketplace orders with multi-parameter filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->with(['buyer.profile', 'courier.profile', 'items.seller.profile', 'payment'])
            ->latest('ordered_at');

        if ($request->filled('order_status')) {
            $query->where('order_status', $request->query('order_status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->query('payment_status'));
        }

        if ($request->boolean('unassigned')) {
            $query->whereNull('courier_id');
        } elseif ($request->filled('courier_id')) {
            $query->where('courier_id', $request->query('courier_id'));
        }

        if ($request->filled('buyer_id')) {
            $query->where('buyer_id', $request->query('buyer_id'));
        }

        if ($request->filled('seller_id')) {
            $sellerId = $request->query('seller_id');
            $query->whereHas('items', fn ($q) => $q->where('seller_id', $sellerId));
        }

        if ($request->filled('search')) {
            $search = strtolower((string) $request->query('search'));
            $query->where(function ($q) use ($search): void {
                $q->where(DB::raw('LOWER(order_number)'), 'like', "%{$search}%")
                    ->orWhereHas('buyer', fn ($bq) => $bq->where('phone', 'like', "%{$search}%")
                        ->orWhere(DB::raw('LOWER(name)'), 'like', "%{$search}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('ordered_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('ordered_at', '<=', $request->query('date_to'));
        }

        $perPage = $request->integer('per_page', 15);
        $orders = $query->paginate($perPage);

        return OrderResource::collection($orders)->response();
    }

    /**
     * Display full details of a specific marketplace order.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['buyer.profile', 'courier.profile', 'items.seller.profile', 'items.product', 'payment.verifier']);

        return (new OrderResource($order))->response();
    }

    /**
     * Manually assign an active field courier to an order and advance status to picking_up if confirmed.
     */
    public function assignCourier(AssignCourierRequest $request, Order $order): JsonResponse
    {
        if ($order->order_status === OrderStatus::Completed || $order->order_status === OrderStatus::Cancelled) {
            return response()->json([
                'message' => "Pesanan dengan status '{$order->order_status->value}' tidak dapat ditugaskan kurir.",
            ], 422);
        }

        $courierId = (int) $request->validated('courier_id');

        DB::transaction(function () use ($order, $courierId): void {
            $updates = ['courier_id' => $courierId];

            if ($order->order_status === OrderStatus::Confirmed) {
                $updates['order_status'] = OrderStatus::PickingUp;
            }

            $order->update($updates);
        });

        $order->load(['buyer.profile', 'courier.profile', 'items.seller.profile']);

        return response()->json([
            'message' => 'Kurir berhasil ditugaskan.',
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Emergency cancellation by admin with automatic inventory restoration.
     */
    public function cancel(AdminCancelOrderRequest $request, Order $order): JsonResponse
    {
        if ($order->order_status === OrderStatus::Cancelled) {
            return response()->json([
                'message' => 'Pesanan ini sudah dibatalkan sebelumnya.',
            ], 422);
        }

        DB::transaction(function () use ($order, $request): void {
            $reason = $request->validated('reason');

            $order->update([
                'order_status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => "Admin: {$reason}",
            ]);

            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                }
            }
        });

        $order->load(['buyer.profile', 'items.seller.profile', 'courier.profile', 'payment']);

        return response()->json([
            'message' => 'Pesanan berhasil dibatalkan oleh admin.',
            'data' => new OrderResource($order),
        ]);
    }
}
