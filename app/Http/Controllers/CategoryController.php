<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
}
