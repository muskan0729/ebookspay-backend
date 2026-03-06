<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Order;
use App\Models\Ebook;
use App\Models\Category;
use App\Models\Coupon;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // ================= GET DASHBOARD SUMMARY =================
    public function summary()
    {
        try {
            $today = Carbon::today();
            $yesterday = Carbon::yesterday();
            $thisMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();
            $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

            // Total Users
            $totalUsers = User::count();
            $newUsersToday = User::whereDate('created_at', $today)->count();
            $newUsersThisMonth = User::where('created_at', '>=', $thisMonth)->count();

            // Total Orders
            $totalOrders = Order::count();
            $ordersToday = Order::whereDate('created_at', $today)->count();
            $ordersThisMonth = Order::where('created_at', '>=', $thisMonth)->count();

            // Sales Revenue
            $totalRevenue = Order::sum('bill_amount');
            $revenueToday = Order::whereDate('created_at', $today)->sum('bill_amount');
            $revenueThisMonth = Order::where('created_at', '>=', $thisMonth)->sum('bill_amount');
            $revenueLastMonth = Order::whereBetween('created_at', [$lastMonth, $endOfLastMonth])->sum('bill_amount');

            // Ebooks
            $totalEbooks = Ebook::count();
            $activeEbooks = Ebook::where('status', 'active')->count();
            $inactiveEbooks = Ebook::where('status', 'inactive')->count();

            // Categories
            $totalCategories = Category::count();

            // Active Coupons
            $activeCoupons = Coupon::where('is_active', true)->count();

            // Recent Orders (last 5)
            $recentOrders = Order::with(['user', 'cart.items.ebook'])
                ->latest()
                ->take(5)
                ->get();

            // Top Selling Ebooks (last 30 days)
            $thirtyDaysAgo = Carbon::now()->subDays(30);
            $topSellingEbooks = Ebook::withCount(['orders' => function($query) use ($thirtyDaysAgo) {
                    $query->where('orders.created_at', '>=', $thirtyDaysAgo);
                }])
                ->orderBy('orders_count', 'desc')
                ->take(5)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Dashboard summary retrieved successfully',
                'data' => [
                    'overview' => [
                        'total_users' => $totalUsers,
                        'total_orders' => $totalOrders,
                        'total_revenue' => floatval($totalRevenue),
                        'total_ebooks' => $totalEbooks,
                        'total_categories' => $totalCategories,
                        'active_coupons' => $activeCoupons,
                    ],
                    'today' => [
                        'new_users' => $newUsersToday,
                        'orders' => $ordersToday,
                        'revenue' => floatval($revenueToday),
                    ],
                    'this_month' => [
                        'new_users' => $newUsersThisMonth,
                        'orders' => $ordersThisMonth,
                        'revenue' => floatval($revenueThisMonth),
                        'revenue_last_month' => floatval($revenueLastMonth),
                    ],
                    'ebooks' => [
                        'active' => $activeEbooks,
                        'inactive' => $inactiveEbooks,
                    ],
                    'recent_orders' => $recentOrders,
                    'top_selling_ebooks' => $topSellingEbooks,
                    'growth' => [
                        'revenue_growth' => $revenueLastMonth > 0 ? 
                            (($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100 : 100,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ================= GET SALES DATA =================
// ================= GET SALES DATA (Simplified) =================
public function sales(Request $request)
{
    try {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $from = $request->input('from') ? Carbon::parse($request->input('from'))->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $to = $request->input('to') ? Carbon::parse($request->input('to'))->endOfDay() : Carbon::now()->endOfDay();

        // Get orders in period
        $orders = Order::whereBetween('created_at', [$from, $to]);
        
        // Simple daily sales (no complex grouping)
        $salesData = Order::whereBetween('created_at', [$from, $to])
            ->selectRaw("
                DATE(created_at) as date,
                COUNT(*) as total_orders,
                SUM(bill_amount) as total_revenue
            ")
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Calculate totals
        $totalOrders = $orders->count();
        $totalRevenue = $orders->sum('bill_amount');
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Get recent orders
        $recentOrders = $orders->with('user')
            ->latest()
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Sales data retrieved successfully',
            'data' => [
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'totals' => [
                    'total_orders' => $totalOrders,
                    'total_revenue' => floatval($totalRevenue),
                    'average_order_value' => floatval($averageOrderValue),
                ],
                'daily_sales' => $salesData,
                'recent_orders' => $recentOrders,
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve sales data',
            'error' => $e->getMessage()
        ], 500);
    }
}
    // ================= GET RECENT ACTIVITY ================= (Optional)
    public function recentActivity()
    {
        try {
            $recentOrders = Order::with('user')
                ->latest()
                ->take(10)
                ->get();

            $recentUsers = User::latest()
                ->take(10)
                ->get();

            $recentEbooks = Ebook::with('categories')
                ->latest()
                ->take(10)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Recent activity retrieved successfully',
                'data' => [
                    'recent_orders' => $recentOrders,
                    'recent_users' => $recentUsers,
                    'recent_ebooks' => $recentEbooks,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recent activity',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}