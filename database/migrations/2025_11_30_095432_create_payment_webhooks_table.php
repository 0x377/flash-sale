<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idempotency_key')->unique();
            $table->foreignUuid('order_id')->constrained()->onDelete('cascade');
            $table->string('payment_provider');
            $table->string('payment_reference')->nullable();
            $table->string('status'); // success, failed, pending
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->json('payload');
            $table->json('provider_response')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            // Advanced indexing for webhook processing
            $table->index(['order_id', 'status']);
            $table->index(['status', 'next_retry_at']);
            $table->index(['payment_reference']);
            $table->index(['created_at']);
            $table->index(['processed_at']);
        });

        // Add failed webhooks archive table
        Schema::create('failed_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->json('webhook_data');
            $table->string('failure_reason');
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();

            $table->index(['last_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_webhooks');
        Schema::dropIfExists('payment_webhooks');
    }
};
