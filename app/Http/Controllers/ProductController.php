<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of available products with filtering and sorting.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->where('is_available', true)
            ->with(['category', 'seller.profile']);

        if ($request->filled('category')) {
            $query->whereHas('category', function ($categoryQuery) use ($request): void {
                $categoryQuery->where('slug', $request->query('category'));
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('seller_id')) {
            $query->where('seller_id', $request->query('seller_id'));
        }

        match ($request->query('sort')) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'newest' => $query->latest(),
            default => $query->latest(),
        };

        $perPage = (int) $request->query('per_page', 15);

        return ProductResource::collection($query->paginate($perPage));
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): ProductResource
    {
        if (! $product->is_available) {
            abort(404, 'Produk tidak ditemukan atau tidak tersedia.');
        }

        $product->load(['category', 'seller.profile']);

        return new ProductResource($product);
    }

    /**
     * Display a listing of the authenticated seller's products.
     */
    public function sellerIndex(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->where('seller_id', $request->user()->id)
            ->with(['category', 'seller.profile']);

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $query->latest();
        $perPage = (int) $request->query('per_page', 15);

        return ProductResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        Gate::authorize('create', Product::class);

        $slug = $this->generateUniqueSlug($request->validated('name'));

        $product = Product::create([
            'seller_id' => $request->user()->id,
            'category_id' => $request->validated('category_id'),
            'name' => $request->validated('name'),
            'slug' => $slug,
            'description' => $request->validated('description'),
            'price' => $request->validated('price'),
            'unit' => $request->validated('unit'),
            'min_order' => $request->validated('min_order', 1),
            'stock' => $request->validated('stock'),
            'image_url' => $request->validated('image_url'),
            'is_available' => $request->validated('is_available', true),
        ]);

        $product->load(['category', 'seller.profile']);

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        Gate::authorize('update', $product);

        $data = $request->validated();

        if (isset($data['name']) && $data['name'] !== $product->name) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], $product->id);
        }

        $product->update($data);
        $product->load(['category', 'seller.profile']);

        return new ProductResource($product);
    }

    /**
     * Remove the specified product from storage (Soft Delete).
     */
    public function destroy(Product $product): JsonResponse
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return response()->json(null, 204);
    }

    /**
     * Generate a unique slug for a product.
     */
    protected function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;

        while (Product::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$baseSlug}-".Str::lower(Str::random(5));
        }

        return $slug;
    }
}
