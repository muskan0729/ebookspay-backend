<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Wishlist;

class WishlistController extends Controller
{
    // GET /api/wishlist
public function index(Request $request)
{
    $user = $request->user();

    $query = Wishlist::with(['ebook' => function ($q) {
        $q->with(['images' => function ($q) {
            $q->limit(1);
        }]);
    }])->where('user_id', $user->id);

    $wishlist = $query->latest()->get();
    $totalCount = $query->count(); // 👈 total count

    return response()->json([
        'status' => true,
        'total_count' => $totalCount,
        'data' => $wishlist
    ]);
}

    // POST /api/wishlist
    // body: { "user_id": 1, "product_id": 5 }  (product_id = ebook_id)
    public function store(Request $request)
    
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:ebooks,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $row = Wishlist::firstOrCreate([
            'user_id' => $request->user_id,
            'ebook_id' => $request->product_id,
        ]);

        return response()->json([
            'status' => true,
            'message' => $row->wasRecentlyCreated ? 'Added to wishlist' : 'Already in wishlist',
            'data' => $row->load('ebook')
        ], 201);
    }

    // DELETE /api/wishlist/{productId}

    public function destroy(Request $request, $productId)
    {
        $user = $request->user(); // Sanctum user
    
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }
    
        $deleted = Wishlist::where('user_id', $user->id)
            ->where('ebook_id', $productId)
            ->delete();
    
        if (!$deleted) {
            return response()->json([
                'status' => false,
                'message' => 'Item not found in wishlist'
            ], 404);
        }
    
        return response()->json([
            'status' => true,
            'message' => 'Removed from wishlist'
        ]);
    }


}