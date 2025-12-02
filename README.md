# Flash Sale Checkout API (Laravel)

## 🚀 Overview

A high-concurrency flash sale API built with Laravel 12, designed to handle limited-stock product sales without overselling. The system implements temporary stock holds, idempotent payment webhooks, and robust race condition handling.

## 🎯 Core Features

- **Real-time Stock Management** - Accurate available stock calculation with caching
- **Temporary Stock Holds** - 2-minute reservations that auto-release on expiry
- **Concurrency-Safe Operations** - Prevents overselling under heavy parallel traffic
- **Idempotent Payment Webhooks** - Handles duplicate and out-of-order webhook deliveries
- **Background Hold Expiry** - Automated stock reclamation using Laravel Horizon/Queue

## 🏗️ Architecture

### Data Flow
1. **Product Inquiry** → GET `/api/products/{id}` (cached stock calculation)
2. **Stock Reservation** → POST `/api/holds` (atomic stock deduction)
3. **Order Creation** → POST `/api/orders` (validates hold)
4. **Payment Processing** → POST `/api/payments/webhook` (idempotent state update)

### Concurrency Strategy
- **Pessimistic Locking** for stock updates using `SELECT FOR UPDATE`
- **Database Transactions** with deadlock retry logic
- **Cache Stampede Prevention** using atomic cache operations
- **Idempotency Keys** for webhook deduplication

## 🛠️ Tech Stack

- **Laravel 12** - PHP Framework
- **MySQL 8+** (InnoDB) - Primary database with row-level locking
- **Redis** - Cache and queue driver (recommended for production)
- **Laravel Horizon** - Queue management for hold expiration
- **Predis** - Redis client for PHP

## 📦 Installation

### Prerequisites
- PHP 8.2+
- Composer 2.5+
- MySQL 8+
- Redis 7+ (optional, can use database cache)

### Setup Steps

```bash
# Clone repository
git clone <repository-url>
cd flash-sale-api

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure environment variables (update .env)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flash_sale
DB_USERNAME=root
DB_PASSWORD=

# Redis configuration (optional but recommended)
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

# Run migrations and seeders
php artisan migrate --seed

# Install and compile frontend assets (if any)
npm install && npm run build

# Start queue worker for hold expiration
php artisan queue:work

# Or use Horizon for better queue management
php artisan horizon

# Start development server
php artisan serve
```

## 🔧 Configuration

### Key Environment Variables
```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flash_checkout
DB_USERNAME=test
DB_PASSWORD=Master@123

# Redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Redis Stock
REDIS_STOCK_HOST=127.0.0.1
REDIS_STOCK_PASSWORD=null
REDIS_STOCK_PORT=6379
REDIS_STOCK_DB=2

# Stock Hold Configuration
HOLD_EXPIRY_MINUTES=2
HOLD_CLEANUP_BATCH_SIZE=50

# Concurrency Settings
STOCK_UPDATE_MAX_RETRIES=3
DEADLOCK_RETRY_DELAY_MS=100

# Cache Configuration
STOCK_CACHE_TTL=30 # seconds
USE_STOCK_CACHE=true

# Webhook Security
WEBHOOK_SECRET=your_webhook_secret
IDEMPOTENCY_KEY_TTL=86400 # 24 hours
```

### Database Schema Highlights

```sql
-- Products table with atomic stock management
products:
  id, name, price, total_stock, available_stock, version (for optimistic locking)

-- Stock holds with expiration
holds:
  id, product_id, quantity, expires_at, status (active/expired/consumed)

-- Orders with payment state
orders:
  id, hold_id, status (pending/paid/cancelled), payment_id

-- Idempotency tracking
idempotency_keys:
  idempotency_key, request_hash, response, created_at
```

## 📡 API Endpoints

### 1. Get Product Details
```http
GET /api/products/{id}
```

**Response:**
```json
{
  "id": 1,
  "name": "Flash Sale Product",
  "price": 99.99,
  "total_stock": 100,
  "available_stock": 85,
  "holds_count": 15
}
```

**Features:**
- Cached available stock calculation
- Real-time stock accuracy with cache invalidation
- Graceful degradation under load

### 2. Create Stock Hold
```http
POST /api/holds
Content-Type: application/json

{
  "product_id": 1,
  "quantity": 2
}
```

**Response (Success):**
```json
{
  "hold_id": "uuid-1234",
  "expires_at": "2024-01-01T12:02:00Z",
  "reserved_for_seconds": 120
}
```

**Concurrency Guarantees:**
- Atomic stock deduction using database transaction
- Deadlock detection and retry
- Immediate availability reduction
- Unique constraint prevents hold reuse

### 3. Create Order
```http
POST /api/orders
Content-Type: application/json

{
  "hold_id": "uuid-1234"
}
```

**Validation Rules:**
- Hold must exist and be active
- Hold must not be expired
- Hold must not already be consumed
- Order status initialized as `pending_payment`

### 4. Payment Webhook (Idempotent)
```http
POST /api/payments/webhook
Content-Type: application/json
X-Idempotency-Key: unique-key-123
X-Webhook-Signature: sha256-hash

{
  "order_reference": "order_123",
  "payment_id": "pay_123",
  "status": "succeeded|failed",
  "timestamp": "2024-01-01T12:00:00Z"
}
```

**Idempotency Features:**
- Deduplication using idempotency keys
- Out-of-order webhook handling
- Atomic state transitions
- Idempotent stock adjustments

## 🛡️ Concurrency & Correctness

### Stock Management Strategies

#### 1. Pessimistic Locking
```php
DB::transaction(function () use ($productId, $quantity) {
    $product = Product::where('id', $productId)
        ->lockForUpdate()
        ->first();
    
    if ($product->available_stock >= $quantity) {
        $product->decrement('available_stock', $quantity);
        return Hold::create([...]);
    }
    
    throw new InsufficientStockException();
}, 3); // Retry up to 3 times on deadlock
```

#### 2. Cache Coherence
- **Write-through cache**: Database updates invalidate cache
- **Cache-aside pattern**: Compute available stock on miss
- **Atomic cache operations**: Prevent cache stampede

#### 3. Hold Expiration
```php
// Scheduled job running every minute
Hold::where('expires_at', '<', now())
    ->where('status', 'active')
    ->chunkById(100, function ($holds) {
        foreach ($holds as $hold) {
            DB::transaction(function () use ($hold) {
                $hold->releaseStock();
                $hold->markAsExpired();
            });
        }
    });
```

### Race Condition Prevention

1. **Database Level**
   - `SELECT FOR UPDATE` for stock operations
   - Unique constraints on hold consumption
   - Optimistic locking with version column

2. **Application Level**
   - Idempotency keys for webhooks
   - State machine validation
   - Request deduplication middleware

3. **Cache Level**
   - Atomic cache operations using Redis Lua scripts
   - Cache versioning for stock data
   - TTL-based invalidation

## 🧪 Testing

### Running Tests
```bash
# Run all tests
php artisan test

# Run concurrency tests only
php artisan test --filter=ConcurrencyTest

# Run with coverage
php artisan test --coverage-html=coverage
```

### Key Test Scenarios

#### 1. Concurrency Tests
```php
public function test_no_oversell_on_stock_boundary()
{
    $product = Product::factory()->create(['total_stock' => 10]);
    
    // Simulate 20 concurrent requests for 1 item each
    Http::fake();
    
    $responses = Concurrently::run(20, function () use ($product) {
        return $this->postJson('/api/holds', [
            'product_id' => $product->id,
            'quantity' => 1
        ]);
    });
    
    $successfulHolds = collect($responses)->filter(fn($r) => $r->successful())->count();
    $this->assertEquals(10, $successfulHolds); // Only 10 should succeed
}
```

#### 2. Webhook Idempotency
```php
public function test_duplicate_webhooks_same_effect()
{
    $order = Order::factory()->create();
    $idempotencyKey = Str::uuid();
    
    // First webhook
    $response1 = $this->postJson('/api/payments/webhook', [
        'order_id' => $order->id,
        'status' => 'paid'
    ], ['X-Idempotency-Key' => $idempotencyKey]);
    
    // Duplicate webhook
    $response2 = $this->postJson('/api/payments/webhook', [
        'order_id' => $order->id,
        'status' => 'paid'
    ], ['X-Idempotency-Key' => $idempotencyKey]);
    
    // Both should succeed but only one should process
    $this->assertEquals($response1->getContent(), $response2->getContent());
    $this->assertEquals(1, PaymentLog::count()); // Only one payment logged
}
```

#### 3. Out-of-Order Webhooks
```php
public function test_webhook_before_order_creation()
{
    // Create hold but delay order creation
    $hold = Hold::factory()->create();
    
    // Webhook arrives before order exists
    $response = $this->postJson('/api/payments/webhook', [
        'hold_id' => $hold->id,
        'status' => 'paid'
    ]);
    
    // System should handle gracefully
    $this->assertEquals(202, $response->getStatusCode()); // Accepted for later processing
    
    // Later create order
    $orderResponse = $this->postJson('/api/orders', [
        'hold_id' => $hold->id
    ]);
    
    // Order should be automatically marked as paid
    $order = Order::first();
    $this->assertEquals('paid', $order->status);
}
```

## 📊 Monitoring & Metrics

### Structured Logging
```json
{
  "timestamp": "2024-01-01T12:00:00Z",
  "context": "stock_hold",
  "product_id": 1,
  "quantity": 2,
  "available_stock_before": 100,
  "available_stock_after": 98,
  "hold_id": "uuid-123",
  "contention_retries": 0,
  "execution_time_ms": 45.2
}
```

### Key Metrics to Track
1. **Stock Contention**
   - Hold creation retry rate
   - Deadlock occurrence count
   - Average hold duration

2. **Webhook Processing**
   - Duplicate webhook rate
   - Out-of-order processing count
   - Average processing latency

3. **System Performance**
   - Cache hit/miss ratio for stock
   - Queue backlog size for hold expiry
   - Database connection pool usage

## 🔄 Background Processing

### Hold Expiration Queue
```php
// In App\Console\Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->job(new ExpireHoldsJob)
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground();
    
    $schedule->command('model:prune', [
        '--model' => [ExpiredHold::class]
    ])->daily();
}
```

### Job Configuration
```php
// config/queue.php
'redis' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'default',
    'retry_after' => 90,
    'block_for' => 5, // Helps prevent queue starvation
],
```

## 🚨 Error Handling

### Custom Exceptions
```php
class InsufficientStockException extends Exception
{
    protected $message = 'Insufficient stock available';
    protected $code = 409; // Conflict
}

class HoldExpiredException extends Exception
{
    protected $message = 'Stock hold has expired';
    protected $code = 410; // Gone
}

class DuplicateWebhookException extends Exception
{
    protected $message = 'Duplicate webhook detected';
    protected $code = 409; // Conflict but already processed
}
```

### Recovery Strategies
1. **Transient Failures**: Exponential backoff retry
2. **Permanent Failures**: Dead letter queue with alerting
3. **Data Corruption**: Database restore from WAL logs
4. **Cache Inconsistency**: Cache warming on recovery

## 📈 Performance Optimization

### Caching Strategy
```php
class StockCacheService
{
    public function getAvailableStock($productId): int
    {
        $key = "product:{$productId}:available_stock";
        
        return Cache::remember($key, 30, function () use ($productId) {
            return $this->calculateAvailableStock($productId);
        });
    }
    
    public function invalidateStockCache($productId): void
    {
        Cache::forget("product:{$productId}:available_stock");
        Cache::tags(["product:{$productId}"])->flush();
    }
}
```

### Database Optimization
```sql
-- Indexes for performance
CREATE INDEX idx_holds_expires_status ON holds(expires_at, status);
CREATE INDEX idx_orders_hold_status ON orders(hold_id, status);
CREATE UNIQUE INDEX idx_idempotency_key ON idempotency_keys(key, request_hash);
```

## 🔒 Security Considerations

### Webhook Security
1. **Signature Verification**: HMAC SHA256 signatures
2. **Idempotency Keys**: UUID-based request deduplication
3. **Rate Limiting**: IP-based and key-based throttling
4. **Payload Validation**: Strict schema validation

### Data Protection
1. **PII Masking**: Payment details never logged
2. **Audit Logging**: All state changes recorded
3. **GDPR Compliance**: Automatic data retention policies
4. **Encryption**: Sensitive data encrypted at rest

## 📚 Assumptions & Invariants

### Core Invariants
1. **Stock Conservation**: `total_stock = available_stock + holds_quantity + orders_quantity`
2. **Hold Uniqueness**: Each hold can only be consumed once
3. **State Consistency**: Order state transitions are monotonic (pending → paid/cancelled)
4. **Idempotency**: Same webhook → same system state

### Business Assumptions
1. Flash sale duration: Typically < 1 hour
2. Maximum concurrent users: 10,000+
3. Hold duration: 2 minutes (configurable)
4. Payment processing time: < 30 seconds
5. Webhook delivery guarantee: At least once

### Technical Assumptions
1. MySQL transaction isolation level: REPEATABLE READ
2. Cache consistency: Eventual consistency acceptable for stock display
3. Clock synchronization: All servers within 1-second skew
4. Network reliability: Intermittent failures handled via retries

## 🚀 Deployment

### Production Checklist
- [ ] Enable query logging for slow operations
- [ ] Configure database connection pooling
- [ ] Set up Redis cluster for high availability
- [ ] Configure Laravel Horizon for queue management
- [ ] Enable OPcache and JIT for PHP
- [ ] Set up APM (New Relic/Datadog)
- [ ] Configure alerting for stock thresholds
- [ ] Implement circuit breakers for external services

### Scaling Considerations
1. **Vertical Scaling**: Increase database memory for lock buffers
2. **Horizontal Scaling**: Stateless API servers behind load balancer
3. **Database Sharding**: Product-based sharding for extreme scale
4. **Read Replicas**: For product listing endpoints

## 🆘 Troubleshooting

### Common Issues

#### 1. Stock Inconsistency
```bash
# Check stock consistency
php artisan stock:verify --product=1

# Recalculate available stock
php artisan stock:recalculate --product=1
```

#### 2. Queue Backlog
```bash
# Check queue status
php artisan horizon:status

# Process stuck jobs
php artisan queue:retry all
```

#### 3. Cache Issues
```bash
# Clear stock cache
php artisan cache:clear --tags=stock

# Warm cache
php artisan stock:cache-warm
```

## 📄 License & Attribution

This project is developed as an interview task solution. Not intended for production use without additional security and scaling considerations.

---

## 🎯 Success Criteria Met

- ✅ No overselling under concurrent load
- ✅ Accurate real-time stock display
- ✅ Automatic hold expiration and stock release
- ✅ Idempotent webhook processing
- ✅ Out-of-order webhook handling
- ✅ Race condition prevention
- ✅ Deadlock handling with retries
- ✅ Cached reads with cache invalidation
- ✅ Background processing for hold expiry
- ✅ Comprehensive test coverage
- ✅ Structured logging and metrics

---

*For questions or issues, please check the issue tracker or submit a pull request.*