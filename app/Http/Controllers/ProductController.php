<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ebook;
use App\Filters\EbookFilter;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductController extends Controller
{
    
    
    public function index(Request $request, EbookFilter $filter)
    {
        try {
            //$query = Ebook::with('categories');
            $query = Ebook::with(['categories', 'images']);
            $filteredQuery = $filter->apply($query, $request->all());
            
            return response()->json([
                'success' => true,
                'data' => $filteredQuery->paginate(12),
                'message' => 'Ebooks retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ebooks',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            //$ebook = Ebook::with('categories')->find($id);
            $ebook = Ebook::with(['categories', 'images'])->find($id);

            
            if (!$ebook) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ebook not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $ebook,
                'message' => 'Ebook retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ebook',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function related($id)
    {
        try {
            $ebook = Ebook::with('categories')->find($id);
            
            if (!$ebook) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ebook not found'
                ], 404);
            }
            
            $categoryIds = $ebook->categories->pluck('id');
            
            // $relatedEbooks = Ebook::whereHas('categories', function ($q) use ($categoryIds) {
            //     $q->whereIn('categories.id', $categoryIds);
            // })
            // ->where('id', '!=', $id)
            // ->limit(5)
            // ->get();
            
          

            $relatedEbooks = Ebook::with(['images', 'categories'])
                ->whereHas('categories', function ($q) use ($categoryIds) {
                    $q->whereIn('categories.id', $categoryIds);
                })
                ->where('id', '!=', $id)
                ->limit(5)
                ->get();

            
            return response()->json([
                'success' => true,
                'data' => $relatedEbooks,
                'message' => 'Related ebooks retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve related ebooks',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function byCategory($id, Request $request, EbookFilter $filter)
    {
        try {
            // $query = Ebook::with('categories')
            //     ->whereHas('categories', function ($q) use ($id) {
            //         $q->where('categories.id', $id);
            //     });
            $query = Ebook::with(['categories', 'images'])
            ->whereHas('categories', function ($q) use ($id) {
                $q->where('categories.id', $id);
            });

            $filteredQuery = $filter->apply($query, $request->all());
    
            $ebooks = $filteredQuery->paginate(12);
            
            // Check if any ebooks found
            if ($ebooks->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => $ebooks,
                    'message' => 'No ebooks found for this category'
                ]);
            }
    
            return response()->json([
                'success' => true,
                'data' => $ebooks,
                'message' => 'Ebooks by category retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ebooks by category',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    
   public function download($id)
{
    $ebook = Ebook::findOrFail($id);
    $user = auth()->user();

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized'
        ], 401);
    }

    $hasPurchased = $user->orders()
        ->whereHas('cart.items', function ($q) use ($id) {
            $q->where('ebook_id', $id);
        })
        ->where('status', 'completed')
        ->exists();

    if (!$hasPurchased) {
        return response()->json([
            'success' => false,
            'message' => 'You have not purchased this ebook'
        ], 403);
    }

    return response()->json([
        'success' => true,
        'download_url' => asset('storage/' . $ebook->ebook_file)
    ]);
}


    
}