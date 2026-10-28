<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Enums\PickupStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\UpdateSellerItemStatusRequest;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerOrderController extends Controller
{
    /**
     * Display a paginated listing of orders containing commodities from this seller's stall.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $seller */
        $seller = $request->user();

        $query = Order::query()
            ->whereHas('items', fn ($q) => $q->where('seller_id', $seller->id))
            ->with(['buyer.profile', 'items.seller.profile', 'courier.profile'])
            ->latest('ordered_at');

        if ($request->filled('order_status')) {
            $query->where('order_status', $request->query('order_status'));
        }

        $perPage = $request->integer('per_page', 15);
        $orders = $query->paginate($perPage);

        return OrderResource::collection($orders)->response();
    }

    /**
     * Display a listing of commodities that must be packed/prepared for courier pickup.
     */
    public function incomingItems(Request $request): JsonResponse
    {
        /** @var User $seller */
        $seller = $request->user();

        $items = OrderItem::query()
            ->where('seller_id', $seller->id)
            ->where('pickup_status', PickupStatus::Waiting)
            ->whereHas('order', fn ($q) => $q->whereIn('order_status', [
                OrderStatus::Confirmed,
                OrderStatus::PickingUp,
            ]))
            ->with(['order.buyer.profile', 'order.courier.profile', 'product'])
            ->latest()
            ->get();

        return OrderItemResource::collection($items)->response();
    }

    /**
     * Update the preparation or stock availability status of a seller's order item.
     */
    public function updateItemStatus(UpdateSellerItemStatusRequest $request, OrderItem $orderItem): JsonResponse
    {
        /** @var User $seller */
        $seller = $request->user();

        if ($orderItem->seller_id !== $seller->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki hak untuk mengubah item dari kios lain.',
            ], 403);
        }

        $orderItem->update([
            'pickup_status' => $request->validated('pickup_status'),
        ]);

        $orderItem->load(['seller.profile', 'product', 'order']);

        return response()->json([
            'message' => 'Status komoditas berhasil diperbarui.',
            'data' => new OrderItemResource($orderItem),
        ]);
    }
}
