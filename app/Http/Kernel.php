<?php

protected $middlewareAliases = [
    // ... existing aliases
    'idempotency' => \App\Http\Middleware\IdempotencyMiddleware::class,
    'concurrency' => \App\Http\Middleware\ConcurrencyControlMiddleware::class,
    'stock.protection' => \App\Http\Middleware\StockProtectionMiddleware::class,
];
