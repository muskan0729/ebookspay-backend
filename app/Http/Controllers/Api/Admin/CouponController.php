<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    // ================= GET ALL COUPONS =================
    public function index()
    {
        try {
            return Coupon::latest()->paginate(10);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ================= CREATE COUPON =================
    public function store(Request $request)
    {
        try {
            $request->validate([
                'code' => 'required|unique:coupons,code',
                'type' => 'required|in:percentage,fixed',
                'value' => 'required|numeric|min:0',
                'min_order_amount' => 'nullable|numeric|min:0',
                'expires_at' => 'nullable|date',
                'is_active' => 'nullable|boolean',
            ]);

            $coupon = Coupon::create($request->all());

            return response()->json([
                'message' => 'Coupon created successfully',
                'coupon' => $coupon
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ================= UPDATE COUPON =================
    public function update(Request $request, $id)
    {
        try {
            $coupon = Coupon::findOrFail($id);

            $request->validate([
                'code' => 'sometimes|unique:coupons,code,' . $id,
                'type' => 'sometimes|in:percentage,fixed',
                'value' => 'sometimes|numeric|min:0',
                'min_order_amount' => 'nullable|numeric|min:0',
                'expires_at' => 'nullable|date',
                'is_active' => 'nullable|boolean',
            ]);

            $coupon->update($request->all());

            return response()->json([
                'message' => 'Coupon updated successfully',
                'coupon' => $coupon
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ================= DELETE COUPON =================
    public function destroy($id)
    {
        try {
            $coupon = Coupon::findOrFail($id);
            $coupon->delete();

            return response()->json([
                'message' => 'Coupon deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}