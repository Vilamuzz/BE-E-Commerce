<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\User\TokoController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\User\BarangController;
use App\Http\Controllers\User\TagihanController;
use App\Http\Controllers\User\LocationController;
use App\Http\Controllers\Admin\KategoriController;
use App\Http\Controllers\User\ChatOfferController;
use App\Http\Controllers\User\KeranjangController;
use App\Http\Controllers\User\PembelianController;
use App\Http\Controllers\User\AlamatTokoController;
use App\Http\Controllers\User\AlamatUserController;
use App\Http\Controllers\User\PesananTokoController;
use App\Http\Controllers\User\ProfileTokoController;
use App\Http\Controllers\User\GambarBarangController;
use App\Http\Controllers\User\SaldoPenjualController;
use App\Http\Controllers\User\DashboardTokoController;
use App\Http\Controllers\Admin\TokoManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\User\DetailPembelianController;
use App\Http\Controllers\Admin\BarangManagementController;
use App\Http\Controllers\Admin\PaymentManagementController;
use App\Http\Controllers\Admin\PesananManagementController;
use App\Http\Controllers\User\PengajuanPencairanController;
use App\Http\Controllers\Admin\KomplainManagementController;

// Auth routes
require __DIR__ . '/auth.php';

// Broadcasting authentication
Route::post('/broadcasting/auth', function (Request $request) {
    if (!Auth::check()) {
        return response()->json(['error' => 'Unauthenticated'], 401);
    }
    return \Illuminate\Support\Facades\Broadcast::auth($request);
})->middleware(['auth:api'])->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

// Public routes - no auth required
Route::prefix('toko')->group(function () {
    Route::get('/slug/{slug}', [TokoController::class, 'getBySlug']);
});

// Public Product Routes
Route::get('/featured-products', [BarangController::class, 'getFeaturedProducts']);
Route::get('/recommended-products', [BarangController::class, 'getRecommendedProducts']);
Route::get('/kategori', [KategoriController::class, 'index']);
Route::get('/products', [BarangController::class, 'getPublicProducts']);
Route::get('/products/{slug}', [BarangController::class, 'getPublicProductBySlug']);

// Region routes
Route::get('/provinces', [RegionController::class, 'getProvinces']);
Route::get('/provinces/{id}/regencies', [RegionController::class, 'getRegencies']);
Route::get('/regencies/{id}/districts', [RegionController::class, 'getDistricts']);
Route::get('/districts/{id}/villages', [RegionController::class, 'getVillages']);

// Public Midtrans notification callback
Route::post('/payments/callback', [TagihanController::class, 'callback']);

// Location routes
Route::prefix('location')->group(function () {
    Route::get('/provinces', [LocationController::class, 'getProvinces']);
    Route::get('/regencies/{province_id}', [LocationController::class, 'getRegencies']);
    Route::get('/districts/{regency_id}', [LocationController::class, 'getDistricts']);
    Route::get('/villages/{district_id}', [LocationController::class, 'getVillages']);
});

// Store profile routes
Route::prefix('store')->group(function () {
    Route::get('/{slug}/profile', [ProfileTokoController::class, 'getStoreProfile']);
    Route::get('/{slug}/products', [ProfileTokoController::class, 'getStoreProducts']);
    Route::get('/{slug}/reviews', [ProfileTokoController::class, 'getStoreReviews']);
    Route::get('/{slug}/categories', [ProfileTokoController::class, 'getStoreCategories']);
});

// Protected routes - require authentication
Route::middleware('auth:api')->group(function () {
    // User profile
    Route::get('/user/profile', [UserController::class, 'getCurrentUser']);
    Route::get('/auth/me', [UserController::class, 'getCurrentUser']);

    // Notification routes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [App\Http\Controllers\User\NotificationController::class, 'index']);
        Route::get('/unread-count', [App\Http\Controllers\User\NotificationController::class, 'getUnreadCount']);
        Route::get('/recent-unread', [App\Http\Controllers\User\NotificationController::class, 'getRecentUnread']);
        Route::post('/{id}/read', [App\Http\Controllers\User\NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [App\Http\Controllers\User\NotificationController::class, 'markAllAsRead']);
        Route::post('/test', [App\Http\Controllers\User\NotificationController::class, 'testNotification']);
    });

    // Toko (Store) management
    Route::prefix('toko')->group(function () {
        Route::get('/my-store', [TokoController::class, 'getMyStore']);
        Route::get('/{id}', [TokoController::class, 'getById'])->where('id', '[0-9]+');
        Route::post('/', [TokoController::class, 'store']);
        Route::put('/', [TokoController::class, 'update']);
        Route::delete('/', [TokoController::class, 'destroy']);
    });

    // Barang (Product) management
    Route::prefix('barang')->group(function () {
        Route::get('/', [BarangController::class, 'index']);
        Route::post('/', [BarangController::class, 'store']);
        Route::get('/slug/{slug}', [BarangController::class, 'getBySlug']);
        Route::get('/{id}', [BarangController::class, 'show'])->where('id', '[0-9]+');
        Route::put('/slug/{slug}', [BarangController::class, 'updateBySlug']);
        Route::put('/{id}', [BarangController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/slug/{slug}', [BarangController::class, 'destroyBySlug']);
        Route::delete('/{id}', [BarangController::class, 'destroy'])->where('id', '[0-9]+');

        // Product images
        Route::get('/{id_barang}/gambar', [GambarBarangController::class, 'index'])->where('id_barang', '[0-9]+');
        Route::get('/slug/{slug}/gambar', [GambarBarangController::class, 'indexByBarangSlug']);
        Route::post('/{id_barang}/gambar', [GambarBarangController::class, 'store'])->where('id_barang', '[0-9]+');
        Route::post('/slug/{slug}/gambar', [GambarBarangController::class, 'storeByBarangSlug']);
        Route::put('/{id_barang}/gambar/{id_gambar}', [GambarBarangController::class, 'update'])->where('id_barang', '[0-9]+');
        Route::put('/slug/{slug}/gambar/{id_gambar}', [GambarBarangController::class, 'updateByBarangSlug']);
        Route::delete('/{id_barang}/gambar/{id_gambar}', [GambarBarangController::class, 'destroy'])->where('id_barang', '[0-9]+');
        Route::delete('/slug/{slug}/gambar/{id_gambar}', [GambarBarangController::class, 'destroyByBarangSlug']);
    });

    // User Address Management
    Route::prefix('user/addresses')->group(function () {
        Route::get('/', [AlamatUserController::class, 'index']);
        Route::get('/{id}', [AlamatUserController::class, 'show']);
        Route::post('/', [AlamatUserController::class, 'store']);
        Route::put('/{id}', [AlamatUserController::class, 'update']);
        Route::delete('/{id}', [AlamatUserController::class, 'destroy']);
        Route::put('/{id}/primary', [AlamatUserController::class, 'setPrimary']);
    });

    // Store Address Management
    Route::prefix('toko/addresses')->group(function () {
        Route::get('/', [AlamatTokoController::class, 'index']);
        Route::get('/{id}', [AlamatTokoController::class, 'show']);
        Route::post('/', [AlamatTokoController::class, 'store']);
        Route::put('/{id}', [AlamatTokoController::class, 'update']);
        Route::delete('/{id}', [AlamatTokoController::class, 'destroy']);
        Route::patch('/{id}/primary', [AlamatTokoController::class, 'setPrimary']);
    });

    // Purchase Management
    Route::prefix('purchases')->group(function () {
        Route::get('/', [PembelianController::class, 'index']);
        Route::post('/', [PembelianController::class, 'store']);
        Route::get('/{kode}', [PembelianController::class, 'show']);
        Route::post('/{kode}/checkout', [PembelianController::class, 'checkout']);
        Route::post('/{kode}/multi-checkout', [PembelianController::class, 'multiCheckout']);
        Route::put('/{kode}/cancel', [PembelianController::class, 'cancel']);
        Route::put('/{kode}/confirm-delivery', [PembelianController::class, 'confirmDelivery']);
        Route::put('/{kode}/complete', [PembelianController::class, 'completePurchase']);

        // Purchase Details
        Route::get('/{kode}/items', [DetailPembelianController::class, 'index']);
        Route::post('/{kode}/items', [DetailPembelianController::class, 'store']);
        Route::get('/{kode}/items/{id}', [DetailPembelianController::class, 'show']);
        Route::put('/{kode}/items/{id}', [DetailPembelianController::class, 'update']);
        Route::delete('/{kode}/items/{id}', [DetailPembelianController::class, 'destroy']);

        // Shipping calculation
        Route::post('/{kode}/calculate-shipping', [PembelianController::class, 'calculateShipping']);
    });

    // Payment Management
    Route::prefix('payments')->group(function () {
        Route::get('/', [TagihanController::class, 'getAll']);
        Route::get('/{kode}', [TagihanController::class, 'show']);
        Route::post('/{kode}/process', [TagihanController::class, 'processPayment']);
        Route::get('/{kode}/status', [TagihanController::class, 'checkStatus']);
    });

    // Cart Management
    Route::prefix('cart')->group(function () {
        Route::get('/', [KeranjangController::class, 'index']);
        Route::post('/', [KeranjangController::class, 'store']);
        Route::put('/{id}', [KeranjangController::class, 'update']);
        Route::delete('/{id}', [KeranjangController::class, 'destroy']);
        Route::post('/select-all', [KeranjangController::class, 'selectAll']);
        Route::post('/checkout', [KeranjangController::class, 'checkout']);
        Route::post('/buy-now', [KeranjangController::class, 'buyNow']);
    });

    // Seller Order Management
    Route::prefix('seller')->group(function () {
        Route::get('/analytics', [DashboardTokoController::class, 'getAnalytics']);
        Route::get('/orders', [PesananTokoController::class, 'index']);
        Route::get('/orders/stats', [PesananTokoController::class, 'getOrderStats']);
        Route::get('/orders/{kode}', [PesananTokoController::class, 'show']);
        Route::post('/orders/{kode}/confirm', [PesananTokoController::class, 'confirmOrder']);
        Route::post('/orders/{kode}/ship', [PesananTokoController::class, 'shipOrder']);

        // Seller Balance Management
        Route::prefix('balance')->group(function () {
            Route::get('/', [SaldoPenjualController::class, 'index']);
            Route::get('/history', [SaldoPenjualController::class, 'getBalanceHistory']);
            Route::post('/hold', [SaldoPenjualController::class, 'holdBalance']);
            Route::post('/release', [SaldoPenjualController::class, 'releaseBalance']);
        });

        // Withdrawal Request Management
        Route::prefix('withdrawals')->group(function () {
            Route::get('/', [PengajuanPencairanController::class, 'index']);
            Route::post('/', [PengajuanPencairanController::class, 'store']);
            Route::get('/{id}', [PengajuanPencairanController::class, 'show']);
            Route::post('/{id}/cancel', [PengajuanPencairanController::class, 'cancel']);
        });
    });

    // Review Management
    Route::prefix('reviews')->group(function () {
        Route::post('/{id_pembelian}', [App\Http\Controllers\User\ReviewController::class, 'store']);
        Route::get('/{id_pembelian}', [App\Http\Controllers\User\ReviewController::class, 'show']);
        Route::delete('/{id_review}', [App\Http\Controllers\User\ReviewController::class, 'destroy']);
        Route::get('/purchase/{id_pembelian}', [App\Http\Controllers\User\ReviewController::class, 'getByPembelian']);
    });

    // Complaint Management
    Route::prefix('komplain')->group(function () {
        Route::post('/{id_pembelian}', [App\Http\Controllers\User\KomplainController::class, 'store']);
        Route::get('/{id_pembelian}', [App\Http\Controllers\User\KomplainController::class, 'show']);
        Route::put('/{id_komplain}', [App\Http\Controllers\User\KomplainController::class, 'update']);
        Route::get('/user/list', [App\Http\Controllers\User\KomplainController::class, 'getByUser']);
    });

    // Retur Management
    Route::prefix('retur')->group(function () {
        Route::post('/', [App\Http\Controllers\User\ReturBarangController::class, 'store']);
        Route::get('/{id_retur}', [App\Http\Controllers\User\ReturBarangController::class, 'show']);
        Route::get('/user/list', [App\Http\Controllers\User\ReturBarangController::class, 'getByUser']);
    });

    // Chat and Offers
    Route::prefix('chat')->group(function () {
        Route::get('/', [App\Http\Controllers\User\RuangChatController::class, 'index']);
        Route::post('/', [App\Http\Controllers\User\RuangChatController::class, 'store']);
        Route::get('/{id}', [App\Http\Controllers\User\RuangChatController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\User\RuangChatController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\User\RuangChatController::class, 'destroy']);
        Route::patch('/{id}/mark-read', [App\Http\Controllers\User\RuangChatController::class, 'markAsRead']);

        // Messages
        Route::get('/{chatRoomId}/messages', [App\Http\Controllers\User\PesanController::class, 'index']);
        Route::post('/{chatRoomId}/messages', [App\Http\Controllers\User\PesanController::class, 'store']);
        Route::put('/messages/{id}', [App\Http\Controllers\User\PesanController::class, 'update']);
        Route::patch('/messages/{id}/read', [App\Http\Controllers\User\PesanController::class, 'markAsRead']);

        // Offers
        Route::post('/{roomId}/offers', [ChatOfferController::class, 'store']);
        Route::post('/offers/{messageId}/respond', [ChatOfferController::class, 'respond']);
        Route::get('/offers/{messageId}/check-purchase', [ChatOfferController::class, 'checkExistingPurchase']);
        Route::post('/offers/{messageId}/purchase', [ChatOfferController::class, 'createPurchaseFromOffer']);
    });

    // Shipping calculation
    Route::post('/shipping/calculate', [App\Http\Controllers\User\ShippingController::class, 'calculateShippingCost']);

    // Admin routes
    Route::middleware('role:admin,superadmin')->group(function () {
        // User management
        Route::prefix('users')->group(function () {
            Route::get('/', [UserManagementController::class, 'index']);
            Route::get('/{id}', [UserManagementController::class, 'show']);
            Route::put('/{id}', [UserManagementController::class, 'update']);
            Route::delete('/{id}', [UserManagementController::class, 'destroy']);
        });

        // Toko management
        Route::prefix('admin/toko')->group(function () {
            Route::get('/', [TokoManagementController::class, 'index']);
            Route::get('/{id}', [TokoManagementController::class, 'show']);
            Route::put('/{id}', [TokoManagementController::class, 'update']);
            Route::delete('/{id}', [TokoManagementController::class, 'destroy']);
            Route::put('/{id}/soft-delete', [TokoManagementController::class, 'softDelete']);
            Route::put('/{id}/restore', [TokoManagementController::class, 'restore']);
        });

        // Kategori management
        Route::prefix('admin/kategori')->group(function () {
            Route::get('/', [KategoriController::class, 'index']);
            Route::post('/', [KategoriController::class, 'store']);
            Route::get('/{id}', [KategoriController::class, 'show']);
            Route::put('/{id}', [KategoriController::class, 'update']);
            Route::delete('/{id}', [KategoriController::class, 'destroy']);
        });

        // Product management
        Route::prefix('admin/barang')->group(function () {
            Route::get('/', [BarangManagementController::class, 'index']);
            Route::get('/filter', [BarangManagementController::class, 'filter']);
            Route::get('/categories', [BarangManagementController::class, 'getCategories']);
            Route::get('/{id}', [BarangManagementController::class, 'show'])->where('id', '[0-9]+');
            Route::get('/slug/{slug}', [BarangManagementController::class, 'showBySlug']);
            Route::put('/{id}', [BarangManagementController::class, 'update'])->where('id', '[0-9]+');
            Route::put('/{id}/soft-delete', [BarangManagementController::class, 'softDelete'])->where('id', '[0-9]+');
            Route::put('/{id}/restore', [BarangManagementController::class, 'restore'])->where('id', '[0-9]+');
            Route::delete('/{id}', [BarangManagementController::class, 'destroy'])->where('id', '[0-9]+');
        });

        // Order management
        Route::prefix('admin/pesanan')->group(function () {
            Route::get('/', [PesananManagementController::class, 'index']);
            Route::get('/stats', [PesananManagementController::class, 'getOrderStats']);
            Route::get('/{kode}', [PesananManagementController::class, 'show']);
            Route::put('/{kode}/status', [PesananManagementController::class, 'updateStatus']);
            Route::post('/{kode}/comment', [PesananManagementController::class, 'addComment']);
        });

        // Seller balance management
        Route::prefix('admin/seller-balance')->group(function () {
            Route::get('/', [SaldoPenjualController::class, 'getAllBalances']);
            Route::get('/{userId}', [SaldoPenjualController::class, 'show']);
        });

        // Payment management
        Route::prefix('admin/payments')->group(function () {
            Route::get('/', [PaymentManagementController::class, 'index']);
            Route::get('/stats', [PaymentManagementController::class, 'getPaymentStats']);
            Route::get('/{kode}', [PaymentManagementController::class, 'show']);
            Route::put('/{kode}/status', [PaymentManagementController::class, 'updateStatus']);
            Route::post('/{kode}/refund', [PaymentManagementController::class, 'processRefund']);
            Route::post('/{kode}/verify', [PaymentManagementController::class, 'verifyManually']);
        });

        // Complaint management
        Route::prefix('admin/komplain')->group(function () {
            Route::get('/', [KomplainManagementController::class, 'index']);
            Route::get('/stats', [KomplainManagementController::class, 'getComplaintStats']);
            Route::get('/{id_komplain}', [KomplainManagementController::class, 'show']);
            Route::post('/{id_komplain}/process', [KomplainManagementController::class, 'processComplaint']);
            Route::post('/{id_komplain}/comment', [KomplainManagementController::class, 'addComment']);
        });

        // Retur management
        Route::prefix('admin/retur')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\ReturBarangManagementController::class, 'index']);
            Route::get('/stats', [App\Http\Controllers\Admin\ReturBarangManagementController::class, 'getReturStats']);
            Route::get('/{id_retur}', [App\Http\Controllers\Admin\ReturBarangManagementController::class, 'show']);
            Route::post('/{id_retur}/process', [App\Http\Controllers\Admin\ReturBarangManagementController::class, 'processRetur']);
        });

        // Withdrawal management
        Route::prefix('admin/pencairan')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\PencairanManagementController::class, 'index']);
            Route::get('/stats', [App\Http\Controllers\Admin\PencairanManagementController::class, 'getPencairanStats']);
            Route::get('/{id_pencairan}', [App\Http\Controllers\Admin\PencairanManagementController::class, 'show']);
            Route::post('/{id_pencairan}/process', [App\Http\Controllers\Admin\PencairanManagementController::class, 'processPencairan']);
            Route::post('/{id_pencairan}/comment', [App\Http\Controllers\Admin\PencairanManagementController::class, 'addComment']);
            Route::post('/bulk-process', [App\Http\Controllers\Admin\PencairanManagementController::class, 'bulkProcess']);
        });

        // Dashboard Analytics
        Route::prefix('dashboard')->group(function () {
            Route::get('/stats', [App\Http\Controllers\Admin\DashboardController::class, 'getStats']);
            Route::get('/revenue-chart', [App\Http\Controllers\Admin\DashboardController::class, 'getRevenueChart']);
            Route::get('/user-growth', [App\Http\Controllers\Admin\DashboardController::class, 'getUserGrowth']);
            Route::get('/top-products', [App\Http\Controllers\Admin\DashboardController::class, 'getTopProducts']);
            Route::get('/recent-activities', [App\Http\Controllers\Admin\DashboardController::class, 'getRecentActivities']);
            Route::get('/order-status-distribution', [App\Http\Controllers\Admin\DashboardController::class, 'getOrderStatusDistribution']);
            Route::get('/payment-methods', [App\Http\Controllers\Admin\DashboardController::class, 'getPaymentMethods']);
            Route::get('/regional-data', [App\Http\Controllers\Admin\DashboardController::class, 'getRegionalData']);
        });
    });
});
