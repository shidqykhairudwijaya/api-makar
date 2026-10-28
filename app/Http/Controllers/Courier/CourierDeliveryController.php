<?php

namespace App\Http\Controllers\Courier;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Courier\UpdateDeliveryStatusRequest;
use App\Http\Requests\Courier\UpdatePickupStatusRequest;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourierDeliveryController extends Controller
{
    /**
     * Display a paginated listing of assigned or available delivery tasks.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $scope = $request->query('scope', 'my_deliveries');

        $query = Order::query()
            ->with(['buyer.profile', 'items.seller.profile', 'payment'])
            ->latest('ordered_at');

        if ($scope === 'available') {
            $query->whereNull('courier_id')
                ->whereIn('order_status', [OrderStatus::Confirmed->value, OrderStatus::PickingUp->value]);
        } else {
            $query->where('courier_id', $courier->id);
        }

        if ($request->filled('order_status')) {
            $query->where('order_status', $request->query('order_status'));
        }

        $perPage = $request->integer('per_page', 15);
        $deliveries = $query->paginate($perPage);

        return OrderResource::collection($deliveries)->response();
    }

    /**
     * Display the specified delivery details including stall pickup points and destination.
     */
    public function show(Order $order): JsonResponse
    {
        /** @var User $courier */
        $courier = auth()->user();

        if ($courier->role !== UserRole::Admin && $order->courier_id !== null && $order->courier_id !== $courier->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke pengantaran ini.',
            ], 403);
        }

        $order->load(['buyer.profile', 'items.seller.profile', 'items.product', 'payment']);

        return (new OrderResource($order))->response();
    }

    /**
     * Claim an unassigned confirmed order for pickup and delivery.
     */
    public function claim(Order $order): JsonResponse
    {
        /** @var User $courier */
        $courier = auth()->user();

        if ($order->courier_id !== null) {
            return response()->json([
                'message' => 'Pesanan ini sudah diambil oleh kurir lain.',
            ], 422);
        }

        if ($order->order_status !== OrderStatus::Confirmed) {
            return response()->json([
                'message' => "Pesanan belum dapat diambil (status saat ini: {$order->order_status->value}).",
            ], 422);
        }

        DB::transaction(function () use ($order, $courier): void {
            $order->update([
                'courier_id' => $courier->id,
                'order_status' => OrderStatus::PickingUp,
            ]);
        });

        $order->load(['buyer.profile', 'items.seller.profile', 'items.product']);

        return response()->json([
            'message' => 'Pesanan berhasil diklaim untuk pengantaran.',
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Update the pickup status of a single order item at a specific seller stall.
     */
    public function updateItemPickup(UpdatePickupStatusRequest $request, OrderItem $orderItem): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $order = $orderItem->order;

        if ($courier->role !== UserRole::Admin && $order->courier_id !== $courier->id) {
            return response()->json([
                'message' => 'Anda bukan kurir yang ditugaskan untuk pesanan ini.',
            ], 403);
        }

        $orderItem->update([
            'pickup_status' => $request->validated('pickup_status'),
        ]);

        $orderItem->load(['seller.profile', 'product']);

        return response()->json([
            'message' => 'Status penjemputan barang berhasil diperbarui.',
            'data' => new OrderItemResource($orderItem),
        ]);
    }

    /**
     * Update the overall delivery status (picking_up -> in_transit -> completed).
     */
    public function updateDeliveryStatus(UpdateDeliveryStatusRequest $request, Order $order): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();

        if ($courier->role !== UserRole::Admin && $order->courier_id !== $courier->id) {
            return response()->json([
                'message' => 'Anda bukan kurir yang ditugaskan untuk pesanan ini.',
            ], 403);
        }

        $nextStatus = OrderStatus::from($request->validated('status'));

        if ($nextStatus === OrderStatus::InTransit && $order->order_status !== OrderStatus::PickingUp) {
            return response()->json([
                'message' => "Pesanan hanya dapat diubah ke 'in_transit' jika status saat ini 'picking_up'.",
            ], 422);
        }

        if ($nextStatus === OrderStatus::Completed && $order->order_status !== OrderStatus::InTransit) {
            return response()->json([
                'message' => "Pesanan hanya dapat diselesaikan ('completed') jika saat ini 'in_transit'.",
            ], 422);
        }

        DB::transaction(function () use ($order, $nextStatus, $courier): void {
            if ($nextStatus === OrderStatus::Completed) {
                $order->update([
                    'order_status' => OrderStatus::Completed,
                    'completed_at' => now(),
                ]);

                // If COD order, mark payment as paid upon delivery completion
                if ($order->payment && $order->payment->payment_method === PaymentMethod::Cod) {
                    $order->update([
                        'payment_status' => PaymentStatus::Paid,
                    ]);

                    $order->payment->update([
                        'paid_at' => now(),
                        'verified_by' => $courier->id,
                    ]);
                }
            } else {
                $order->update([
                    'order_status' => OrderStatus::InTransit,
                ]);
            }
        });

        $order->load(['buyer.profile', 'items.seller.profile', 'courier.profile', 'payment']);

        return response()->json([
            'message' => "Status pengantaran berhasil diperbarui menjadi {$nextStatus->value}.",
            'data' => new OrderResource($order),
        ]);
    }
}
