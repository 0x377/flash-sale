<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enhanced cache table for stock caching
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
            $table->index(['expiration']);
        });

        // Stock cache table for fast reads
        Schema::create('product_stock_cache', function (Blueprint $table) {
            $table->foreignId('product_id')->primary()->constrained()->onDelete('cascade');
            $table->integer('available_stock');
            $table->integer('held_stock');
            $table->integer('total_holds');
            $table->timestamp('cached_at');
            $table->timestamp('refreshed_at');

            $table->index(['cached_at']);
        });

        // Failed jobs with enhanced tracking
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->string('failure_reason')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('failed_at')->useCurrent();
            
            $table->index(['failed_at']);
            $table->index(['queue']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_cache');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('cache');
    }
};
