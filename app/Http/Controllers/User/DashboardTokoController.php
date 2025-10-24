<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Toko;
use App\Models\Barang;
use App\Models\DetailPembelian;
use App\Models\Pembelian;
use App\Models\SaldoPenjual;
use App\Models\Review;
use Carbon\Carbon;

class DashboardTokoController extends Controller
{
    /**
     * Get comprehensive analytics data for store dashboard
     */
    public function getAnalytics(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Get seller's shop
            $toko = Toko::where('id_user', $user->id_user)
                        ->where('is_deleted', false)
                        ->first();
            
            if (!$toko) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Toko tidak ditemukan'
                ], 404);
            }

            // Get date range from request or default to last 30 days
            $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : Carbon::now();
            $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::now()->subDays(30);

            $analytics = [
                'overview' => $this->getOverviewStats($toko->id_toko, $startDate, $endDate),
                'sales_trend' => $this->getSalesTrend($toko->id_toko, $startDate, $endDate),
                'top_products' => $this->getTopProducts($toko->id_toko, $startDate, $endDate),
                'order_status_distribution' => $this->getOrderStatusDistribution($toko->id_toko),
                'revenue_analytics' => $this->getRevenueAnalytics($toko->id_toko, $startDate, $endDate),
                'customer_analytics' => $this->getCustomerAnalytics($toko->id_toko, $startDate, $endDate),
                'product_performance' => $this->getProductPerformance($toko->id_toko),
                'recent_activities' => $this->getRecentActivities($toko->id_toko)
            ];

            return response()->json([
                'status' => 'success',
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            \Log::error('Error getting analytics: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch analytics data',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats($tokoId, $startDate, $endDate)
    {
        try {
            // Total revenue in date range
            $currentRevenue = DetailPembelian::where('id_toko', $tokoId)
                ->whereHas('pembelian', function($q) use ($startDate, $endDate) {
                    $q->where('status_pembelian', 'Selesai')
                      ->whereBetween('updated_at', [$startDate, $endDate]);
                })
                ->sum('subtotal') ?? 0;

            // Previous period revenue for comparison
            $daysDiff = $startDate->diffInDays($endDate);
            $previousStartDate = $startDate->copy()->subDays($daysDiff);
            
            $previousRevenue = DetailPembelian::where('id_toko', $tokoId)
                ->whereHas('pembelian', function($q) use ($previousStartDate, $startDate) {
                    $q->where('status_pembelian', 'Selesai')
                      ->whereBetween('updated_at', [$previousStartDate, $startDate]);
                })
                ->sum('subtotal') ?? 0;

            // Total orders
            $currentOrders = DetailPembelian::where('id_toko', $tokoId)
                ->whereHas('pembelian', function($q) use ($startDate, $endDate) {
                    $q->whereNotIn('status_pembelian', ['Draft', 'Menunggu Pembayaran'])
                      ->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->distinct('id_pembelian')
                ->count('id_pembelian') ?? 0;

            // Previous period orders
            $previousOrders = DetailPembelian::where('id_toko', $tokoId)
                ->whereHas('pembelian', function($q) use ($previousStartDate, $startDate) {
                    $q->whereNotIn('status_pembelian', ['Draft', 'Menunggu Pembayaran'])
                      ->whereBetween('created_at', [$previousStartDate, $startDate]);
                })
                ->distinct('id_pembelian')
                ->count('id_pembelian') ?? 0;

            // Total products
            $totalProducts = Barang::where('id_toko', $tokoId)
                                  ->where('is_deleted', false)
                                  ->count() ?? 0;

            // Available balance
            $saldoPenjual = SaldoPenjual::where('id_user', Auth::user()->id_user)->first();
            $availableBalance = $saldoPenjual ? $saldoPenjual->saldo_tersedia : 0;

            // Calculate growth percentages with better handling
            $revenueGrowth = 0;
            if ($previousRevenue > 0) {
                $revenueGrowth = (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
            } elseif ($currentRevenue > 0) {
                $revenueGrowth = 100; // 100% growth if previous was 0 but current has value
            }

            $ordersGrowth = 0;
            if ($previousOrders > 0) {
                $ordersGrowth = (($currentOrders - $previousOrders) / $previousOrders) * 100;
            } elseif ($currentOrders > 0) {
                $ordersGrowth = 100; // 100% growth if previous was 0 but current has value
            }

            return [
                'total_revenue' => (float) $currentRevenue,
                'revenue_growth' => round($revenueGrowth, 1),
                'total_orders' => (int) $currentOrders,
                'orders_growth' => round($ordersGrowth, 1),
                'total_products' => (int) $totalProducts,
                'available_balance' => (float) $availableBalance
            ];
        } catch (\Exception $e) {
            \Log::error('Error in getOverviewStats: ' . $e->getMessage());
            return [
                'total_revenue' => 0.0,
                'revenue_growth' => 0.0,
                'total_orders' => 0,
                'orders_growth' => 0.0,
                'total_products' => 0,
                'available_balance' => 0.0
            ];
        }
    }

    /**
     * Get sales trend data for charts
     */
    private function getSalesTrend($tokoId, $startDate, $endDate)
    {
        try {
            $salesByDay = DB::table('detail_pembelian')
                ->join('pembelian', 'detail_pembelian.id_pembelian', '=', 'pembelian.id_pembelian')
                ->where('detail_pembelian.id_toko', $tokoId)
                ->where('pembelian.status_pembelian', 'Selesai')
                ->whereBetween('pembelian.updated_at', [$startDate, $endDate])
                ->selectRaw('DATE(pembelian.updated_at) as date, SUM(detail_pembelian.subtotal) as revenue, COUNT(DISTINCT detail_pembelian.id_pembelian) as orders')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return $salesByDay->map(function($item) {
                return [
                    'date' => $item->date,
                    'revenue' => (float) $item->revenue,
                    'orders' => (int) $item->orders
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Error in getSalesTrend: ' . $e->getMessage());
            return [];
        }
    }

    // Removed duplicate getTopProducts method to resolve redeclaration error.

    /**
     * Alternative simpler approach for getTopProducts
     */
    private function getTopProducts($tokoId, $startDate, $endDate)
    {
        try {
            // First, let's get all completed orders for this shop in the date range
            $completedOrderDetails = DetailPembelian::where('id_toko', $tokoId)
                ->whereHas('pembelian', function($q) use ($startDate, $endDate) {
                    $q->where('status_pembelian', 'Selesai')
                      ->whereBetween('updated_at', [$startDate, $endDate]);
                })
                ->with(['barang.gambarBarang' => function($q) {
                    $q->where('is_primary', true);
                }])
                ->get();

            \Log::info('Completed order details found:', [
                'count' => $completedOrderDetails->count(),
                'toko_id' => $tokoId,
                'date_range' => [$startDate, $endDate]
            ]);

            if ($completedOrderDetails->isEmpty()) {
                return [];
            }

            // Group by product and calculate stats
            $productStats = [];
            foreach ($completedOrderDetails as $detail) {
                $productId = $detail->id_barang;
                
                if (!isset($productStats[$productId])) {
                    $productStats[$productId] = [
                        'product' => $detail->barang,
                        'total_sold' => 0,
                        'total_revenue' => 0
                    ];
                }
                
                $productStats[$productId]['total_sold']++;
                $productStats[$productId]['total_revenue'] += $detail->subtotal;
            }

            // Convert to array and sort by price (highest first)
            $result = array_values($productStats);
            usort($result, function($a, $b) {
                return $b['product']->harga - $a['product']->harga;
            });

            // Take top 5 and format for frontend
            $topProducts = array_slice($result, 0, 5);
            
            return array_map(function($item) {
                return [
                    'product' => [
                        'id_barang' => $item['product']->id_barang,
                        'nama_barang' => $item['product']->nama_barang,
                        'harga' => $item['product']->harga,
                        'gambarBarang' => $item['product']->gambarBarang ?? []
                    ],
                    'total_sold' => (int) $item['total_sold'],
                    'total_revenue' => (float) $item['total_revenue']
                ];
            }, $topProducts);

        } catch (\Exception $e) {
            \Log::error('Error in getTopProducts: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get order status distribution
     */
    private function getOrderStatusDistribution($tokoId)
    {
        try {
            $statusDistribution = DB::table('detail_pembelian')
                ->join('pembelian', 'detail_pembelian.id_pembelian', '=', 'pembelian.id_pembelian')
                ->where('detail_pembelian.id_toko', $tokoId)
                ->whereNotIn('pembelian.status_pembelian', ['Draft', 'Menunggu Pembayaran'])
                ->selectRaw('pembelian.status_pembelian, COUNT(DISTINCT detail_pembelian.id_pembelian) as count')
                ->groupBy('pembelian.status_pembelian')
                ->get();

            return $statusDistribution->map(function($item) {
                return [
                    'status' => $item->status_pembelian,
                    'count' => (int) $item->count
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Error in getOrderStatusDistribution: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get revenue analytics with monthly breakdown
     */
    private function getRevenueAnalytics($tokoId, $startDate, $endDate)
    {
        try {
            $monthlyRevenue = DB::table('detail_pembelian')
                ->join('pembelian', 'detail_pembelian.id_pembelian', '=', 'pembelian.id_pembelian')
                ->where('detail_pembelian.id_toko', $tokoId)
                ->where('pembelian.status_pembelian', 'Selesai')
                ->whereBetween('pembelian.updated_at', [$startDate, $endDate])
                ->selectRaw('YEAR(pembelian.updated_at) as year, MONTH(pembelian.updated_at) as month, SUM(detail_pembelian.subtotal) as revenue')
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get();

            return $monthlyRevenue->map(function($item) {
                return [
                    'period' => Carbon::create($item->year, $item->month)->format('M Y'),
                    'revenue' => (float) $item->revenue
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Error in getRevenueAnalytics: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get customer analytics
     */
    private function getCustomerAnalytics($tokoId, $startDate, $endDate)
    {
        try {
            // Unique customers
            $uniqueCustomers = DB::table('detail_pembelian')
                ->join('pembelian', 'detail_pembelian.id_pembelian', '=', 'pembelian.id_pembelian')
                ->where('detail_pembelian.id_toko', $tokoId)
                ->whereNotIn('pembelian.status_pembelian', ['Draft', 'Menunggu Pembayaran'])
                ->whereBetween('pembelian.created_at', [$startDate, $endDate])
                ->distinct('pembelian.id_pembeli')
                ->count('pembelian.id_pembeli') ?? 0;

            // Repeat customers
            $repeatCustomers = DB::table('detail_pembelian')
                ->join('pembelian', 'detail_pembelian.id_pembelian', '=', 'pembelian.id_pembelian')
                ->where('detail_pembelian.id_toko', $tokoId)
                ->whereNotIn('pembelian.status_pembelian', ['Draft', 'Menunggu Pembayaran'])
                ->whereBetween('pembelian.created_at', [$startDate, $endDate])
                ->selectRaw('pembelian.id_pembeli, COUNT(DISTINCT detail_pembelian.id_pembelian) as order_count')
                ->groupBy('pembelian.id_pembeli')
                ->havingRaw('COUNT(DISTINCT detail_pembelian.id_pembelian) > 1')
                ->count() ?? 0;

            $retentionRate = 0;
            if ($uniqueCustomers > 0) {
                $retentionRate = ($repeatCustomers / $uniqueCustomers) * 100;
            }

            return [
                'unique_customers' => (int) $uniqueCustomers,
                'repeat_customers' => (int) $repeatCustomers,
                'retention_rate' => round($retentionRate, 1)
            ];
        } catch (\Exception $e) {
            \Log::error('Error in getCustomerAnalytics: ' . $e->getMessage());
            return [
                'unique_customers' => 0,
                'repeat_customers' => 0,
                'retention_rate' => 0.0
            ];
        }
    }

    /**
     * Get product performance metrics
     */
    private function getProductPerformance($tokoId)
    {
        try {
            $totalProducts = Barang::where('id_toko', $tokoId)
                                  ->where('is_deleted', false)
                                  ->count() ?? 0;

            $soldProducts = Barang::where('id_toko', $tokoId)
                                 ->where('is_deleted', false)
                                 ->whereExists(function($query) {
                                     $query->select(DB::raw(1))
                                           ->from('detail_pembelian')
                                           ->whereRaw('detail_pembelian.id_barang = barang.id_barang');
                                 })
                                 ->count() ?? 0;

            // Get average rating for this store's products
            $averageRating = DB::table('review')
                ->join('pembelian', 'review.id_pembelian', '=', 'pembelian.id_pembelian')
                ->join('detail_pembelian', 'pembelian.id_pembelian', '=', 'detail_pembelian.id_pembelian')
                ->where('detail_pembelian.id_toko', $tokoId)
                ->avg('review.rating') ?? 0;

            $conversionRate = 0;
            if ($totalProducts > 0) {
                $conversionRate = ($soldProducts / $totalProducts) * 100;
            }

            return [
                'total_products' => (int) $totalProducts,
                'sold_products' => (int) $soldProducts,
                'conversion_rate' => round($conversionRate, 1),
                'average_rating' => $averageRating ? round($averageRating, 1) : 0.0
            ];
        } catch (\Exception $e) {
            \Log::error('Error in getProductPerformance: ' . $e->getMessage());
            return [
                'total_products' => 0,
                'sold_products' => 0,
                'conversion_rate' => 0.0,
                'average_rating' => 0.0
            ];
        }
    }

    /**
     * Get recent activities
     */
    private function getRecentActivities($tokoId)
    {
        try {
            $activities = DetailPembelian::where('id_toko', $tokoId)
                ->whereHas('pembelian', function($q) {
                    $q->whereNotIn('status_pembelian', ['Draft', 'Menunggu Pembayaran']);
                })
                ->with(['pembelian.pembeli', 'barang'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return $activities->map(function($detail) {
                return [
                    'type' => 'order',
                    'message' => "Pesanan baru dari " . ($detail->pembelian->pembeli->name ?? 'Unknown'),
                    'product' => $detail->barang->nama_barang ?? 'Unknown Product',
                    'amount' => (float) $detail->subtotal,
                    'status' => $detail->pembelian->status_pembelian ?? 'Unknown',
                    'created_at' => $detail->created_at
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Error in getRecentActivities: ' . $e->getMessage());
            return [];
        }
    }
}
