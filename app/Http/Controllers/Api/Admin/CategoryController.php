<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
{
    return Category::withCount('ebooks')->latest()->get();
}
public function show($id)
{
    return Category::with('ebooks')->findOrFail($id);
}

public function store(Request $request)
{
    $request->validate([
        'name' => 'required|unique:categories,name'
    ]);

    $category = Category::create([
        'name' => $request->name,
        'slug' => Str::slug($request->name),
    ]);

    return response()->json([
        'message' => 'Category created',
        'category' => $category
    ], 201);
}

public function update(Request $request, $id)
{
    $category = Category::findOrFail($id);

    $request->validate([
        'name' => 'required|unique:categories,name,' . $category->id
    ]);

    $category->update([
        'name' => $request->name,
        'slug' => Str::slug($request->name),
    ]);

    return response()->json([
        'message' => 'Category updated',
        'category' => $category
    ]);
}
    public function destroy($id)
{
    $category = Category::findOrFail($id);

    if ($category->ebooks()->count() > 0) {
        return response()->json([
            'message' => 'Category is linked to ebooks and cannot be deleted'
        ], 409);
    }

    $category->delete();

    return response()->json([
        'message' => 'Category deleted'
    ]);
}

}



