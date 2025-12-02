<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_holds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('quantity');
            $table->string('session_id')->nullable();
            $table->string('status')->default('pending'); // pending, consumed, expired, cancelled
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            // Advanced indexing for performance
            $table->index(['product_id', 'status', 'expires_at']);
            $table->index(['status', 'expires_at']);
            $table->index(['expires_at']);
            $table->index(['session_id']);
            $table->index(['created_at']);
        });

        // Materialized view for available stock calculation (concurrent reads)
        DB::statement("
            CREATE VIEW product_available_stock AS
            SELECT 
                p.id,
                p.initial_stock,
                p.available_stock,
                COALESCE(SUM(CASE 
                    WHEN sh.status = 'pending' AND sh.expires_at > NOW() 
                    THEN sh.quantity 
                    ELSE 0 
                END), 0) as held_stock,
                (p.available_stock - COALESCE(SUM(CASE 
                    WHEN sh.status = 'pending' AND sh.expires_at > NOW() 
                    THEN sh.quantity 
                    ELSE 0 
                END), 0)) as current_available
            FROM products p
            LEFT JOIN stock_holds sh ON p.id = sh.product_id
            GROUP BY p.id, p.initial_stock, p.available_stock
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS product_available_stock');
        Schema::dropIfExists('stock_holds');
    }
};
