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
        return Order::with(['user', 'cart.items.ebook'])
            ->latest()
            ->paginate(10);
    }

    // ================= GET SINGLE ORDER =================
    public function show($id)
    {
        return Order::with(['user', 'cart.items.ebook'])
            ->findOrFail($id);
    }

    // ================= UPDATE ORDER STATUS =================
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,completed,cancelled,refunded'
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order
        ]);
    }

    // ================= REFUND ORDER =================
    public function refund($id)
    {
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
    }
}


