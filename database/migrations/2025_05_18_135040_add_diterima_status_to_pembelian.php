<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing constraint if it exists
        DB::statement("ALTER TABLE pembelian DROP CONSTRAINT IF EXISTS pembelian_status_pembelian_check");
        
        // Convert column to VARCHAR first
        DB::statement("ALTER TABLE pembelian ALTER COLUMN status_pembelian TYPE VARCHAR(255)");
        
        // Add the new constraint with all status values including 'Diterima'
        DB::statement("
            ALTER TABLE pembelian 
            ADD CONSTRAINT pembelian_status_pembelian_check 
            CHECK (status_pembelian IN ('Draft', 'Menunggu Pembayaran', 'Dibayar', 'Diproses', 'Dikirim', 'Diterima', 'Selesai', 'Dibatalkan'))
        ");
    }

    public function down(): void
    {
        // Drop the constraint
        DB::statement("ALTER TABLE pembelian DROP CONSTRAINT IF EXISTS pembelian_status_pembelian_check");
        
        // Revert back to original enum values (without 'Diterima')
        DB::statement("
            ALTER TABLE pembelian 
            ADD CONSTRAINT pembelian_status_pembelian_check 
            CHECK (status_pembelian IN ('Draft', 'Menunggu Pembayaran', 'Dibayar', 'Diproses', 'Dikirim', 'Selesai', 'Dibatalkan'))
        ");
    }
};
