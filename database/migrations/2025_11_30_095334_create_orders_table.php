<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('stock_hold_id')->constrained('stock_holds')->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->string('status')->default('pending'); // pending, paid, cancelled, failed
            $table->string('customer_email')->nullable();
            $table->json('customer_details')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // Comprehensive indexing
            $table->index(['status', 'created_at']);
            $table->index(['stock_hold_id']);
            $table->index(['customer_email']);
            $table->index(['paid_at']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
