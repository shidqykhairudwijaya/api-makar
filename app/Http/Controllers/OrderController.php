<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PickupStatus;
use App\Http\Requests\Order\CancelOrderRequest;
use App\Http\Requests\Order\CheckoutOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * Display a listing of orders placed by the authenticated buyer or all orders for admin.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Order::query()
            ->with(['items.seller.profile', 'items.product', 'courier.profile', 'payment'])
            ->latest('ordered_at');

        if (! $user->role || $user->role->value !== 'admin') {
            $query->where('buyer_id', $user->id);
        }

        if ($request->filled('order_status')) {
            $query->where('order_status', $request->query('order_status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->query('payment_status'));
        }

        $perPage = $request->integer('per_page', 15);
        $orders = $query->paginate($perPage);

        return OrderResource::collection($orders)->response();
    }

    /**
     * Display the specified order details.
     */
    public function show(Order $order): JsonResponse
    {
        Gate::authorize('view', $order);

        $order->load(['buyer.profile', 'items.seller.profile', 'items.product', 'courier.profile', 'payment.verifier']);

        return (new OrderResource($order))->response();
    }

    /**
     * Checkout items in the buyer's cart into a new confirmed order.
     */
    public function checkout(CheckoutOrderRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cartItems = Cart::query()
            ->where('buyer_id', $user->id)
            ->with(['product.seller.profile'])
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Keranjang belanja Anda masih kosong.',
            ], 422);
        }

        // Validate stock, availability, and minimum order requirements
        foreach ($cartItems as $item) {
            $product = $item->product;

            if (! $product || ! $product->is_available) {
                return response()->json([
                    'message' => "Produk '".($product?->name ?? 'item')."' saat ini tidak tersedia untuk dipesan.",
                ], 422);
            }

            if ($item->quantity < $product->min_order) {
                $unitName = $product->unit?->value ?? 'satuan';

                return response()->json([
                    'message' => "Jumlah pesanan untuk '{$product->name}' kurang dari batas minimum pembelian ({$product->min_order} {$unitName}).",
                ], 422);
            }

            if ($item->quantity > $product->stock) {
                return response()->json([
                    'message' => "Stok untuk produk '{$product->name}' tidak mencukupi (tersisa {$product->stock}).",
                ], 422);
            }
        }

        // Resolve delivery address snapshot
        $addressSnapshot = $request->validated('delivery_address');

        if (empty($addressSnapshot)) {
            $profile = $user->profile;

            if (! $profile || empty($profile->full_address)) {
                return response()->json([
                    'message' => 'Alamat pengiriman belum diatur. Silakan lengkapi profil Anda atau masukkan alamat pengiriman pada saat checkout.',
                ], 422);
            }

            $addressSnapshot = [
                'recipient_name' => $user->name,
                'phone' => $user->phone,
                'store_name' => $profile->store_name,
                'market_name' => $profile->market_name,
                'block_number' => $profile->block_number,
                'full_address' => $profile->full_address,
                'latitude' => $profile->latitude,
                'longitude' => $profile->longitude,
            ];
        }

        $subtotalProducts = $cartItems->sum(fn ($item) => (int) ($item->quantity * ($item->product?->price ?? 0)));
        $deliveryFee = (int) ($request->validated('delivery_fee') ?? 20000);
        $totalAmount = $subtotalProducts + $deliveryFee;

        $orderNumber = $this->generateUniqueOrderNumber();

        $order = DB::transaction(function () use (
            $user,
            $orderNumber,
            $subtotalProducts,
            $deliveryFee,
            $totalAmount,
            $addressSnapshot,
            $request,
            $cartItems
        ): Order {
            $order = Order::create([
                'order_number' => $orderNumber,
                'buyer_id' => $user->id,
                'courier_id' => null,
                'subtotal_products' => $subtotalProducts,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $totalAmount,
                'order_status' => OrderStatus::Pending,
                'payment_status' => PaymentStatus::Unpaid,
                'delivery_address_snapshot' => $addressSnapshot,
                'notes' => $request->validated('notes'),
                'ordered_at' => now(),
            ]);

            foreach ($cartItems as $item) {
                $product = $item->product;
                $priceAtPurchase = (int) $product->price;
                $quantity = (int) $item->quantity;
                $subtotal = $priceAtPurchase * $quantity;

                $order->items()->create([
                    'product_id' => $product->id,
                    'seller_id' => $product->seller_id,
                    'product_name_snapshot' => $product->name,
                    'unit_snapshot' => $product->unit?->value ?? 'satuan',
                    'price_at_purchase' => $priceAtPurchase,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                    'pickup_status' => PickupStatus::Waiting,
                ]);

                // Atomically decrement stock
                $product->decrement('stock', $quantity);
            }

            // Clear buyer's cart after successful order creation
            Cart::where('buyer_id', $user->id)->delete();

            return $order;
        });

        $order->load(['buyer.profile', 'items.seller.profile', 'items.product']);

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Cancel a pending order and restore inventory back to sellers.
     */
    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('cancel', $order);

        if ($order->order_status !== OrderStatus::Pending) {
            return response()->json([
                'message' => "Pesanan dengan status '{$order->order_status->value}' tidak dapat dibatalkan.",
            ], 422);
        }

        DB::transaction(function () use ($order, $request): void {
            $order->update([
                'order_status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $request->validated('reason') ?? 'Dibatalkan oleh pembeli.',
            ]);

            // Restore product stock
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::where('id', $item->product_id)->increment('stock', $item->quantity);
                }
            }
        });

        $order->load(['buyer.profile', 'items.seller.profile', 'items.product']);

        return response()->json([
            'message' => 'Pesanan berhasil dibatalkan.',
            'data' => new OrderResource($order),
        ]);
    }

    /**
     * Generate a unique order tracking number.
     */
    protected function generateUniqueOrderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
