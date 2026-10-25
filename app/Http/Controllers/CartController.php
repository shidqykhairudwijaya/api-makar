<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\UpdateCartRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CartController extends Controller
{
    /**
     * Display a listing of the buyer's cart items with pricing summary.
     */
    public function index(Request $request): JsonResponse
    {
        $items = Cart::query()
            ->where('buyer_id', $request->user()->id)
            ->with(['product.category', 'product.seller.profile'])
            ->latest()
            ->get();

        $totalItems = $items->sum('quantity');
        $totalPrice = $items->sum(fn ($item) => (int) ($item->quantity * ($item->product?->price ?? 0)));

        return response()->json([
            'data' => CartResource::collection($items),
            'summary' => [
                'total_items' => (int) $totalItems,
                'total_price' => (int) $totalPrice,
                'formatted_total_price' => 'Rp '.number_format((float) $totalPrice, 0, ',', '.'),
            ],
        ]);
    }

    /**
     * Add a product to the buyer's cart or increment quantity.
     */
    public function store(AddToCartRequest $request): JsonResponse
    {
        $product = Product::findOrFail($request->validated('product_id'));

        if (! $product->is_available) {
            return response()->json([
                'message' => 'Produk tidak tersedia untuk dibeli.',
            ], 422);
        }

        if ($product->seller_id === $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak dapat membeli produk dari kios Anda sendiri.',
            ], 422);
        }

        $existingCart = Cart::where('buyer_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->first();

        $requestedQuantity = (int) $request->validated('quantity');
        $newQuantity = $existingCart ? $existingCart->quantity + $requestedQuantity : $requestedQuantity;

        if ($newQuantity < $product->min_order) {
            $unitName = $product->unit?->value ?? 'satuan';

            return response()->json([
                'message' => "Jumlah pembelian minimal untuk produk ini adalah {$product->min_order} {$unitName}.",
            ], 422);
        }

        if ($newQuantity > $product->stock) {
            return response()->json([
                'message' => "Jumlah melebihi stok yang tersedia (sisa stok: {$product->stock}).",
            ], 422);
        }

        $cart = Cart::updateOrCreate(
            [
                'buyer_id' => $request->user()->id,
                'product_id' => $product->id,
            ],
            [
                'quantity' => $newQuantity,
            ]
        );

        $cart->load(['product.category', 'product.seller.profile']);

        return (new CartResource($cart))
            ->response()
            ->setStatusCode($existingCart ? 200 : 201);
    }

    /**
     * Update the quantity of a specific cart item.
     */
    public function update(UpdateCartRequest $request, Cart $cart): JsonResponse
    {
        Gate::authorize('update', $cart);

        $product = $cart->product ?? Product::findOrFail($cart->product_id);

        if (! $product->is_available) {
            return response()->json([
                'message' => 'Produk ini sedang tidak tersedia.',
            ], 422);
        }

        $newQuantity = (int) $request->validated('quantity');

        if ($newQuantity < $product->min_order) {
            $unitName = $product->unit?->value ?? 'satuan';

            return response()->json([
                'message' => "Jumlah pembelian minimal untuk produk ini adalah {$product->min_order} {$unitName}.",
            ], 422);
        }

        if ($newQuantity > $product->stock) {
            return response()->json([
                'message' => "Jumlah melebihi stok yang tersedia (sisa stok: {$product->stock}).",
            ], 422);
        }

        $cart->update(['quantity' => $newQuantity]);
        $cart->load(['product.category', 'product.seller.profile']);

        return (new CartResource($cart))
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Remove a specific item from the cart.
     */
    public function destroy(Cart $cart): JsonResponse
    {
        Gate::authorize('delete', $cart);

        $cart->delete();

        return response()->json([
            'message' => 'Item berhasil dihapus dari keranjang.',
        ]);
    }

    /**
     * Remove all items from the buyer's cart.
     */
    public function clear(Request $request): JsonResponse
    {
        Cart::where('buyer_id', $request->user()->id)->delete();

        return response()->json([
            'message' => 'Keranjang belanja berhasil dikosongkan.',
        ]);
    }
}
