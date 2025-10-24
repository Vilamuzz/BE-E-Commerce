<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Toko;    
use App\Models\Barang;
use App\Models\Pembelian;
use App\Models\Tagihan;
use App\Models\PengajuanPencairan;
use App\Models\Komplain;

class DashboardController extends Controller
{
    public function getStats(Request $request)
    {
        try {
            $period = $request->input('period', '30'); // days
            
            // Calculate date range
            $endDate = Carbon::now();
            $startDate = Carbon::now()->subDays($period);
            $startOfMonth = Carbon::now()->startOfMonth();
            $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
            $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

            // Overview stats - exclude Draft status
            $totalUsers = User::where('role', '!=', 1)->count();
            $totalStores = Toko::where('is_deleted', false)->count();
            $totalProducts = Barang::where('is_deleted', false)->count();
            $totalOrders = Pembelian::where('is_deleted', false)
                ->where('status_pembelian', '!=', 'Draft')
                ->count();

            // Revenue calculation - exclude Draft status
            $totalRevenue = DB::table('tagihan as t')
                ->join('pembelian as p', 't.id_pembelian', '=', 'p.id_pembelian')
                ->where('t.status_pembayaran', 'Dibayar')
                ->where('p.is_deleted', false)
                ->where('p.status_pembelian', '!=', 'Draft')
                ->sum('t.total_tagihan');

            $monthlyRevenue = DB::table('tagihan as t')
                ->join('pembelian as p', 't.id_pembelian', '=', 'p.id_pembelian')
                ->where('t.status_pembayaran', 'Dibayar')
                ->where('t.created_at', '>=', $startOfMonth)
                ->where('p.is_deleted', false)
                ->where('p.status_pembelian', '!=', 'Draft')
                ->sum('t.total_tagihan');

            // Growth stats - exclude Draft status
            $newUsersThisMonth = User::where('role', '!=', 1)
                ->where('created_at', '>=', $startOfMonth)
                ->count();

            $newOrdersThisMonth = Pembelian::where('is_deleted', false)
                ->where('status_pembelian', '!=', 'Draft')
                ->where('created_at', '>=', $startOfMonth)
                ->count();

            // Previous month comparisons - exclude Draft status
            $newUsersLastMonth = User::where('role', '!=', 1)
                ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
                ->count();

            $newOrdersLastMonth = Pembelian::where('is_deleted', false)
                ->where('status_pembelian', '!=', 'Draft')
                ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
                ->count();

            // Calculate growth percentages
            $userGrowthPercentage = $newUsersLastMonth > 0 
                ? (($newUsersThisMonth - $newUsersLastMonth) / $newUsersLastMonth) * 100 
                : ($newUsersThisMonth > 0 ? 100 : 0);

            $orderGrowthPercentage = $newOrdersLastMonth > 0 
                ? (($newOrdersThisMonth - $newOrdersLastMonth) / $newOrdersLastMonth) * 100 
                : ($newOrdersThisMonth > 0 ? 100 : 0);

            // Pending items - exclude Draft status
            $pendingPayments = Tagihan::join('pembelian as p', 'tagihan.id_pembelian', '=', 'p.id_pembelian')
                ->where('tagihan.status_pembayaran', 'Menunggu')
                ->where('p.is_deleted', false)
                ->where('p.status_pembelian', '!=', 'Draft')
                ->count();

            $pendingWithdrawals = PengajuanPencairan::where('status_pencairan', 'Menunggu')->count();
            $pendingComplaints = Komplain::where('status_komplain', 'Menunggu')->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'overview' => [
                        'total_users' => $totalUsers,
                        'total_stores' => $totalStores,
                        'total_products' => $totalProducts,
                        'total_orders' => $totalOrders,
                        'total_revenue' => $totalRevenue,
                        'monthly_revenue' => $monthlyRevenue
                    ],
                    'growth' => [
                        'new_users_this_month' => $newUsersThisMonth,
                        'new_orders_this_month' => $newOrdersThisMonth,
                        'user_growth_percentage' => round($userGrowthPercentage, 2),
                        'order_growth_percentage' => round($orderGrowthPercentage, 2)
                    ],
                    'pending_items' => [
                        'pending_payments' => $pendingPayments,
                        'pending_withdrawals' => $pendingWithdrawals,
                        'pending_complaints' => $pendingComplaints
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch dashboard stats: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getRevenueChart(Request $request)
    {
        try {
            $period = $request->input('period', '30'); // days
            
            $revenueData = DB::table('tagihan as t')
                ->join('pembelian as p', 't.id_pembelian', '=', 'p.id_pembelian')
                ->select(
                    DB::raw('DATE(t.created_at) as date'),
                    DB::raw('SUM(CASE WHEN t.status_pembayaran = "Dibayar" THEN t.total_tagihan ELSE 0 END) as revenue'),
                    DB::raw('COUNT(CASE WHEN t.status_pembayaran = "Dibayar" THEN 1 END) as successful_payments'),
                    DB::raw('COUNT(*) as total_payments')
                )
                ->where('t.created_at', '>=', Carbon::now()->subDays($period))
                ->where('p.is_deleted', false)
                ->where('p.status_pembelian', '!=', 'Draft')
                ->groupBy(DB::raw('DATE(t.created_at)'))
                ->orderBy('date')
                ->get();

            $chartData = [];
            for ($i = $period - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i)->format('Y-m-d');
                
                $existingData = $revenueData->first(function ($item) use ($date) {
                    return $item->date === $date;
                });
                
                $chartData[] = [
                    'date' => $date,
                    'revenue' => $existingData ? $existingData->revenue : 0,
                    'successful_payments' => $existingData ? $existingData->successful_payments : 0,
                    'total_payments' => $existingData ? $existingData->total_payments : 0,
                    'formatted_date' => Carbon::parse($date)->format('d M')
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => $chartData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch revenue chart: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getUserGrowth(Request $request)
    {
        try {
            $period = $request->input('period', '12'); // months
            
            $userGrowthData = DB::table('users')
                ->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('COUNT(*) as total_users'),
                    DB::raw('COUNT(CASE WHEN role = 2 THEN 1 END) as regular_users'),
                    DB::raw('COUNT(CASE WHEN role = 2 AND EXISTS(SELECT 1 FROM toko WHERE toko.id_user = users.id_user) THEN 1 END) as seller_users')
                )
                ->where('created_at', '>=', Carbon::now()->subMonths($period))
                ->where('role', '!=', 1) // Exclude admin users
                ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
                ->orderBy('year')
                ->orderBy('month')
                ->get();

            $chartData = [];
            for ($i = $period - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $year = $date->year;
                $month = $date->month;
                
                $existingData = $userGrowthData->first(function ($item) use ($year, $month) {
                    return $item->year == $year && $item->month == $month;
                });
                
                $chartData[] = [
                    'date' => $date->format('Y-m'),
                    'total_users' => $existingData ? (int)$existingData->total_users : 0,
                    'regular_users' => $existingData ? (int)$existingData->regular_users : 0,
                    'seller_users' => $existingData ? (int)$existingData->seller_users : 0,
                    'formatted_date' => $date->format('M Y')
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => $chartData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch user growth: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getRecentActivities(Request $request)
    {
        try {
            $limit = $request->input('limit', 15);
            
            // Get recent orders from pembelian table - exclude Draft status
            $recentOrders = DB::table('pembelian as p')
                ->join('users as u', 'p.id_pembeli', '=', 'u.id_user')
                ->leftJoin('tagihan as t', 'p.id_pembelian', '=', 't.id_pembelian')
                ->select(
                    'p.kode_pembelian',
                    'u.name as customer_name',
                    DB::raw('COALESCE(t.total_tagihan, 0) as total_harga'),
                    'p.status_pembelian',
                    'p.created_at',
                    DB::raw('"order" as activity_type')
                )
                ->where('p.is_deleted', false)
                ->where('p.status_pembelian', '!=', 'Draft')
                ->orderBy('p.created_at', 'desc')
                ->limit($limit);

            // Get recent payments from tagihan table - exclude Draft orders
            $recentPayments = DB::table('tagihan as t')
                ->join('pembelian as p', 't.id_pembelian', '=', 'p.id_pembelian')
                ->join('users as u', 'p.id_pembeli', '=', 'u.id_user')
                ->select(
                    't.kode_tagihan as kode_pembelian',
                    'u.name as customer_name',
                    't.total_tagihan as total_harga',
                    't.status_pembayaran as status_pembelian',
                    't.created_at',
                    DB::raw('"payment" as activity_type')
                )
                ->where('p.is_deleted', false)
                ->where('p.status_pembelian', '!=', 'Draft')
                ->orderBy('t.created_at', 'desc')
                ->limit($limit);

            // Combine and sort both queries
            $recentActivities = $recentOrders->union($recentPayments)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $recentActivities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch recent activities: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getOrderStatusDistribution(Request $request)
    {
        try {
            $statusDistribution = DB::table('pembelian as p')
                ->leftJoin('tagihan as t', 'p.id_pembelian', '=', 't.id_pembelian')
                ->select(
                    'p.status_pembelian',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(CASE WHEN t.status_pembayaran = "Dibayar" THEN t.total_tagihan ELSE 0 END) as total_value')
                )
                ->where('p.is_deleted', false)
                ->where('p.status_pembelian', '!=', 'Draft')
                ->groupBy('p.status_pembelian')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $statusDistribution
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch order status distribution: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getPaymentMethods(Request $request)
    {
        try {
            $paymentMethods = DB::table('tagihan')
                ->select(
                    'metode_pembayaran',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(total_tagihan) as total_amount')
                )
                ->where('status_pembayaran', 'Dibayar')
                ->whereNotNull('metode_pembayaran')
                ->groupBy('metode_pembayaran')
                ->orderBy('count', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $paymentMethods
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch payment methods: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getRegionalData(Request $request)
    {
        try {
            $regionalData = DB::table('pembelian as p')
                ->join('alamat_user as au', 'p.id_alamat', '=', 'au.id_alamat')
                ->join('provinces as prov', 'au.provinsi', '=', 'prov.id')
                ->select(
                    'prov.name as province_name',
                    DB::raw('COUNT(*) as order_count'),
                    DB::raw('SUM(CASE WHEN EXISTS(SELECT 1 FROM tagihan t WHERE t.id_pembelian = p.id_pembelian AND t.status_pembayaran = "Dibayar") THEN (SELECT t2.total_tagihan FROM tagihan t2 WHERE t2.id_pembelian = p.id_pembelian LIMIT 1) ELSE 0 END) as total_revenue')
                )
                ->where('p.is_deleted', false)
                ->where('p.status_pembelian', '!=', 'Draft')
                ->groupBy('prov.id', 'prov.name')
                ->orderBy('order_count', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $regionalData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch regional data: ' . $e->getMessage()
            ], 500);
        }
    }
}
