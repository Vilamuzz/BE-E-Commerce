<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id('id_notifikasi');
            $table->unsignedBigInteger('id_user');
            $table->enum('tipe_notifikasi', [
                'Penawaran Baru', 
                'Penawaran Diterima', 
                'Penawaran Ditolak', 
                'Pembayaran Diterima', 
                'Pengiriman', 
                'Komplain', 
                'Transaksi Selesai', 
                'Pencairan',
                'Pesanan Baru',
                'Status Pesanan'
            ]);
            $table->text('isi_notifikasi');
            $table->json('data')->nullable(); // Additional data for the notification
            $table->string('action_url')->nullable(); // URL to redirect when notification is clicked
            $table->boolean('is_read')->default(false);
            $table->timestamps();
            
            $table->foreign('id_user')->references('id_user')->on('users')->onDelete('cascade');
            $table->index(['id_user', 'is_read']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
    }
};
