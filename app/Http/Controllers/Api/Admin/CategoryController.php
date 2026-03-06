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
        try {
            return Category::withCount('ebooks')->latest()->get();
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function show($id)
    {
        try {
            return Category::with('ebooks')->findOrFail($id);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        
        try {
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
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id) 
    {
        try {
            $category = Category::findOrFail($id);

            $request->validate([
                'name' => 'sometimes|required|unique:categories,name,' . $category->id
            ]);

            $data = [];

            if ($request->has('name')) { // <- use has() instead of filled()
                $data['name'] = $request->name;
                $data['slug'] = Str::slug($request->name);
            }

            if (!empty($data)) {
                $category->update($data);
            }

            return response()->json([
                'message' => 'Category updated',
                'category' => $category
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
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
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}