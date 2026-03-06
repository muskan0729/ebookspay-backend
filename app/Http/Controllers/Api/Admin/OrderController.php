<?php 
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    // ================= GET ALL ORDERS =================
    public function index()
    {
        try {
            // return("enter");
            return Order::with(['user', 'cart.items.ebook'])
    ->latest()
    ->get();
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ================= GET SINGLE ORDER =================
    public function show($id)
    {
        try {
            return Order::with(['user', 'cart.items.ebook'])
                ->findOrFail($id);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ================= UPDATE ORDER STATUS =================
    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,completed,cancelled,refunded'
            ]);

            $order = Order::findOrFail($id);
            $order->update(['status' => $request->status]);

            return response()->json([
                'message' => 'Order status updated successfully',
                'order' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ================= REFUND ORDER =================
    public function refund($id)
    {
        try {
            $order = Order::findOrFail($id);

            if ($order->status !== 'cancelled') {
                return response()->json([
                    'message' => 'Only cancelled orders can be refunded'
                ], 422);
            }

            $order->update(['status' => 'refunded']);

            return response()->json([
                'message' => 'Order refunded successfully',
                'order' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}