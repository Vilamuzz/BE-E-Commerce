<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PostgreSQL compatible syntax
        DB::statement("ALTER TABLE komplain DROP CONSTRAINT IF EXISTS komplain_status_komplain_check");
        DB::statement("
            ALTER TABLE komplain 
            ADD CONSTRAINT komplain_status_komplain_check 
            CHECK (status_komplain IN ('Menunggu', 'Diproses', 'Selesai', 'Ditolak'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE komplain DROP CONSTRAINT IF EXISTS komplain_status_komplain_check");
        DB::statement("
            ALTER TABLE komplain 
            ADD CONSTRAINT komplain_status_komplain_check 
            CHECK (status_komplain IN ('Menunggu', 'Diproses', 'Selesai'))
        ");
    }
};
