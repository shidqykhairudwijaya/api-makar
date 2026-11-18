<?php

namespace App\Http\Controllers;

use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of all categories with product counts.
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::withCount(['products' => function ($query): void {
            $query->where('is_available', true);
        }])->orderBy('name')->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Store a newly created commodity category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $name = (string) $request->validated('name');
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;

        while (Category::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-".Str::lower(Str::random(5));
        }

        $category = Category::create([
            'name' => $name,
            'slug' => $slug,
            'image_url' => $request->validated('image_url'),
        ]);

        return response()->json([
            'message' => 'Kategori komoditas berhasil ditambahkan.',
            'data' => new CategoryResource($category),
        ], 201);
    }
}
