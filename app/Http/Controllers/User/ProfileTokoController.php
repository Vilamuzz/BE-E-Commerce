<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Toko;
use App\Models\Barang;
use App\Models\Review;
use App\Models\Pembelian;
use App\Models\DetailPembelian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileTokoController extends Controller
{
    /**
     * Get store profile by slug
     */
    public function getStoreProfile($slug)
    {
        try {
            $toko = Toko::where('slug', $slug)
                ->where('is_active', true)
                ->where('is_deleted', false)
                ->with([
                    'user:id_user,name,email',
                    'alamat_toko' => function($query) {
                        $query->where('is_primary', true)
                              ->with(['province', 'regency', 'district']);
                    }
                ])
                ->first();

            if (!$toko) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store not found'
                ], 404);
            }

            // Get store statistics
            $stats = $this->getStoreStatistics($toko->id_toko);

            // Get store rating
            $rating = $this->getStoreRating($toko->id_toko);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'store' => $toko,
                    'statistics' => $stats,
                    'rating' => $rating
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch store profile',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get store products with filtering and pagination
     */
    public function getStoreProducts(Request $request, $slug)
    {
        try {
            $toko = Toko::where('slug', $slug)
                ->where('is_active', true)
                ->where('is_deleted', false)
                ->first();

            if (!$toko) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store not found'
                ], 404);
            }

            $query = Barang::where('id_toko', $toko->id_toko)
                ->where('is_deleted', false)
                ->where('status_barang', 'Tersedia')
                ->with([
                    'kategori:id_kategori,nama_kategori,slug',
                    'gambarBarang' => function($query) {
                        $query->where('is_primary', true)->orderBy('urutan', 'asc');
                    }
                ]);

            // Apply filters
            if ($request->has('category') && $request->category) {
                $query->whereHas('kategori', function($q) use ($request) {
                    $q->where('slug', $request->category);
                });
            }

            if ($request->has('search') && $request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('nama_barang', 'like', "%{$request->search}%")
                      ->orWhere('deskripsi_barang', 'like', "%{$request->search}%");
                });
            }

            if ($request->has('min_price') && $request->min_price) {
                $query->where('harga', '>=', $request->min_price);
            }

            if ($request->has('max_price') && $request->max_price) {
                $query->where('harga', '<=', $request->max_price);
            }

            if ($request->has('grade') && $request->grade) {
                $query->where('grade', $request->grade);
            }

            // Apply sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            
            $allowedSortFields = ['created_at', 'harga', 'nama_barang', 'stok'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            $perPage = $request->input('per_page', 12);
            $products = $query->paginate($perPage);

            // Transform the response to match frontend expectations
            $response = [
                'status' => 'success',
                'data' => [
                    'data' => $products->items(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ]
            ];

            return response()->json($response);

        } catch (\Exception $e) {
            \Log::error('Store products fetch error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch store products',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get store reviews
     */
    public function getStoreReviews(Request $request, $slug)
    {
        try {
            $toko = Toko::where('slug', $slug)
                ->where('is_active', true)
                ->where('is_deleted', false)
                ->first();

            if (!$toko) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store not found'
                ], 404);
            }

            $query = Review::whereHas('pembelian.detailPembelian', function($q) use ($toko) {
                $q->where('id_toko', $toko->id_toko);
            })
            ->with([
                'user:id_user,name',
                'pembelian' => function($query) use ($toko) {
                    $query->select('id_pembelian', 'kode_pembelian')
                          ->with(['detailPembelian' => function($q) use ($toko) {
                              $q->where('id_toko', $toko->id_toko)
                                ->with([
                                    'barang' => function($query) {
                                        $query->select('id_barang', 'nama_barang')
                                              ->with(['gambarBarang' => function($q) {
                                                  $q->where('is_primary', true)
                                                    ->orderBy('urutan', 'asc')
                                                    ->select('id_gambar', 'id_barang', 'url_gambar', 'is_primary', 'urutan');
                                              }]);
                                    }
                                ])
                                ->select('id_detail', 'id_pembelian', 'id_barang', 'id_toko');
                          }]);
                }
            ])
            ->orderBy('created_at', 'desc');

            // Filter by rating if specified
            if ($request->has('rating') && $request->rating) {
                $query->where('rating', $request->rating);
            }

            $perPage = $request->input('per_page', 10);
            $reviews = $query->paginate($perPage);

            // Transform the response to ensure consistent naming and proper image URLs
            $transformedData = $reviews->toArray();
            $transformedData['data'] = array_map(function($review) {
                // Ensure both camelCase and snake_case versions exist for compatibility
                if (isset($review['pembelian'])) {
                    $detailPembelian = $review['pembelian']['detailPembelian'] ?? $review['pembelian']['detail_pembelian'] ?? [];
                    
                    // Process each detail to ensure proper image URLs
                    $detailPembelian = array_map(function($detail) {
                        if (isset($detail['barang']['gambarBarang'])) {
                            $detail['barang']['gambarBarang'] = array_map(function($gambar) {
                                // Ensure the URL is complete
                                if (!str_starts_with($gambar['url_gambar'], 'http')) {
                                    $gambar['url_gambar'] = url('storage/' . $gambar['url_gambar']);
                                }
                                return $gambar;
                            }, $detail['barang']['gambarBarang']);
                            
                            // Also add snake_case version for compatibility
                            $detail['barang']['gambar_barang'] = $detail['barang']['gambarBarang'];
                        }
                        return $detail;
                    }, $detailPembelian);
                    
                    // Add both versions for maximum compatibility
                    $review['pembelian']['detailPembelian'] = $detailPembelian;
                    $review['pembelian']['detail_pembelian'] = $detailPembelian;
                }
                
                // Process review image if exists
                if (isset($review['image_review']) && $review['image_review']) {
                    if (!str_starts_with($review['image_review'], 'http')) {
                        $review['image_review'] = url('storage/' . $review['image_review']);
                    }
                }
                
                return $review;
            }, $transformedData['data']);

            return response()->json([
                'status' => 'success',
                'data' => $transformedData
            ]);

        } catch (\Exception $e) {
            \Log::error('Store reviews fetch error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch store reviews',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get store categories
     */
    public function getStoreCategories($slug)
    {
        try {
            $toko = Toko::where('slug', $slug)
                ->where('is_active', true)
                ->where('is_deleted', false)
                ->first();

            if (!$toko) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store not found'
                ], 404);
            }

            $categories = DB::table('kategori')
                ->join('barang', 'kategori.id_kategori', '=', 'barang.id_kategori')
                ->where('barang.id_toko', $toko->id_toko)
                ->where('barang.is_deleted', false)
                ->where('barang.status_barang', 'Tersedia')
                ->select('kategori.*', DB::raw('COUNT(barang.id_barang) as product_count'))
                ->groupBy('kategori.id_kategori', 'kategori.nama_kategori', 'kategori.slug', 'kategori.logo', 'kategori.is_active', 'kategori.is_deleted', 'kategori.created_by', 'kategori.updated_by', 'kategori.created_at', 'kategori.updated_at')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $categories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch store categories',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get store statistics
     */
    private function getStoreStatistics($tokoId)
    {
        // Total products
        $totalProducts = Barang::where('id_toko', $tokoId)
            ->where('is_deleted', false)
            ->where('status_barang', 'Tersedia')
            ->count();

        // Total orders completed - Fixed column name
        $totalOrdersCompleted = DetailPembelian::where('id_toko', $tokoId)
            ->whereHas('pembelian', function($q) {
                $q->where('status_pembelian', 'Selesai'); // Fixed: was 'status'
            })
            ->distinct('id_pembelian')
            ->count('id_pembelian');

        // Total revenue (completed orders only) - Fixed column name
        $totalRevenue = DetailPembelian::where('id_toko', $tokoId)
            ->whereHas('pembelian', function($q) {
                $q->where('status_pembelian', 'Selesai'); // Fixed: was 'status'
            })
            ->sum('subtotal');

        // Store join date
        $storeJoinDate = Toko::where('id_toko', $tokoId)->value('created_at');

        // Average response time (this would need a messaging system)
        $avgResponseTime = '< 1 hour'; // Placeholder

        // Success rate (completed vs total orders) - Fixed column name
        $totalOrders = DetailPembelian::where('id_toko', $tokoId)
            ->distinct('id_pembelian')
            ->count('id_pembelian');

        $successRate = $totalOrders > 0 ? round(($totalOrdersCompleted / $totalOrders) * 100, 1) : 0;

        return [
            'total_products' => $totalProducts,
            'total_orders_completed' => $totalOrdersCompleted,
            'total_revenue' => $totalRevenue,
            'success_rate' => $successRate,
            'store_join_date' => $storeJoinDate,
            'avg_response_time' => $avgResponseTime
        ];
    }

    /**
     * Get store rating statistics
     */
    private function getStoreRating($tokoId)
    {
        $reviews = Review::whereHas('pembelian.detailPembelian', function($q) use ($tokoId) {
            $q->where('id_toko', $tokoId);
        })->get();

        if ($reviews->isEmpty()) {
            return [
                'average_rating' => 0,
                'total_reviews' => 0,
                'rating_distribution' => [
                    5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0
                ]
            ];
        }

        $totalReviews = $reviews->count();
        $averageRating = round($reviews->avg('rating'), 1);

        $ratingDistribution = [
            5 => $reviews->where('rating', 5)->count(),
            4 => $reviews->where('rating', 4)->count(),
            3 => $reviews->where('rating', 3)->count(),
            2 => $reviews->where('rating', 2)->count(),
            1 => $reviews->where('rating', 1)->count(),
        ];

        return [
            'average_rating' => $averageRating,
            'total_reviews' => $totalReviews,
            'rating_distribution' => $ratingDistribution
        ];
    }
}
