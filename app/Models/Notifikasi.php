<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    use HasFactory;

    protected $table = 'notifikasi';
    protected $primaryKey = 'id_notifikasi';
    
    protected $fillable = [
        'id_user',
        'tipe_notifikasi',
        'isi_notifikasi',
        'data',
        'action_url',
        'is_read'
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean'
    ];

    /**
     * Relationship with User
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for recent notifications
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        $this->update(['is_read' => true]);
    }

    /**
     * Create a new notification for a user
     */
    public static function createForUser($userId, $type, $message, $data = null, $actionUrl = null)
    {
        return self::create([
            'id_user' => $userId,
            'tipe_notifikasi' => $type,
            'isi_notifikasi' => $message,
            'data' => $data,
            'action_url' => $actionUrl
        ]);
    }

    /**
     * Create notification for order status change
     */
    public static function createOrderStatusNotification($userId, $orderCode, $newStatus, $actionUrl = null)
    {
        $messages = [
            'Dibayar' => "Pembayaran untuk pesanan {$orderCode} telah diterima",
            'Diproses' => "Pesanan {$orderCode} sedang diproses oleh penjual",
            'Dikirim' => "Pesanan {$orderCode} telah dikirim",
            'Diterima' => "Pesanan {$orderCode} telah diterima",
            'Selesai' => "Transaksi pesanan {$orderCode} telah selesai",
            'Dibatalkan' => "Pesanan {$orderCode} telah dibatalkan"
        ];

        $message = $messages[$newStatus] ?? "Status pesanan {$orderCode} telah diperbarui menjadi {$newStatus}";

        return self::createForUser(
            $userId,
            'Status Pesanan',
            $message,
            ['order_code' => $orderCode, 'status' => $newStatus],
            $actionUrl
        );
    }

    /**
     * Create notification for new offer
     */
    public static function createOfferNotification($sellerId, $buyerName, $productName, $offerPrice, $actionUrl = null)
    {
        $message = "{$buyerName} membuat penawaran Rp " . number_format($offerPrice, 0, ',', '.') . " untuk {$productName}";

        return self::createForUser(
            $sellerId,
            'Penawaran Baru',
            $message,
            ['buyer_name' => $buyerName, 'product_name' => $productName, 'offer_price' => $offerPrice],
            $actionUrl
        );
    }

    /**
     * Create notification for offer response
     */
    public static function createOfferResponseNotification($buyerId, $productName, $status, $actionUrl = null)
    {
        $statusText = $status === 'Diterima' ? 'diterima' : 'ditolak';
        $message = "Penawaran Anda untuk {$productName} telah {$statusText}";

        return self::createForUser(
            $buyerId,
            $status === 'Diterima' ? 'Penawaran Diterima' : 'Penawaran Ditolak',
            $message,
            ['product_name' => $productName, 'status' => $status],
            $actionUrl
        );
    }
}
