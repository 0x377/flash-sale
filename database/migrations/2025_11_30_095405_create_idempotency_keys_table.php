<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('resource_type'); // webhook, hold, order, etc.
            $table->string('resource_id')->nullable();
            $table->json('request_params')->nullable();
            $table->json('response')->nullable();
            $table->integer('response_code')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // High-performance indexing
            $table->index(['key', 'resource_type']);
            $table->index(['resource_type', 'resource_id']);
            $table->index(['completed_at']);
            $table->index(['locked_at']);
        });

        // Add idempotency key to orders for webhook tracking
        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('webhook_idempotency_key')->nullable()->after('id');
            $table->uuid('payment_idempotency_key')->nullable()->after('webhook_idempotency_key');

            $table->index(['webhook_idempotency_key']);
            $table->index(['payment_idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['webhook_idempotency_key', 'payment_idempotency_key']);
        });
        Schema::dropIfExists('idempotency_keys');
    }
};
