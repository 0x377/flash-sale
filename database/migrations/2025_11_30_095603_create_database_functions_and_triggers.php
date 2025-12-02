<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Function to safely reserve stock
        DB::statement("
            CREATE FUNCTION try_reserve_stock(
                p_product_id BIGINT,
                p_quantity INT,
                p_hold_duration_minutes INT DEFAULT 2
            ) RETURNS UUID
            DETERMINISTIC
            BEGIN
                DECLARE v_available_stock INT;
                DECLARE v_hold_id UUID;
                DECLARE v_expires_at TIMESTAMP;

                SELECT available_stock INTO v_available_stock 
                FROM products 
                WHERE id = p_product_id AND is_active = TRUE
                FOR UPDATE;

                IF v_available_stock >= p_quantity THEN
                    SET v_hold_id = UUID();
                    SET v_expires_at = NOW() + INTERVAL p_hold_duration_minutes MINUTE;

                    UPDATE products 
                    SET available_stock = available_stock - p_quantity 
                    WHERE id = p_product_id;

                    INSERT INTO stock_holds (id, product_id, quantity, expires_at, created_at, updated_at)
                    VALUES (v_hold_id, p_product_id, p_quantity, v_expires_at, NOW(), NOW());

                    RETURN v_hold_id;
                ELSE
                    RETURN NULL;
                END IF;
            END
        ");

        // Trigger to update stock cache
        DB::statement("
            CREATE TRIGGER after_stock_hold_insert
            AFTER INSERT ON stock_holds
            FOR EACH ROW
            BEGIN
                INSERT INTO product_stock_cache (product_id, available_stock, held_stock, total_holds, cached_at, refreshed_at)
                VALUES (
                    NEW.product_id,
                    (SELECT available_stock FROM products WHERE id = NEW.product_id),
                    (SELECT COALESCE(SUM(quantity), 0) FROM stock_holds WHERE product_id = NEW.product_id AND status = 'pending' AND expires_at > NOW()),
                    (SELECT COUNT(*) FROM stock_holds WHERE product_id = NEW.product_id AND status = 'pending'),
                    NOW(),
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    available_stock = VALUES(available_stock),
                    held_stock = VALUES(held_stock),
                    total_holds = VALUES(total_holds),
                    refreshed_at = NOW();
            END
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS after_stock_hold_insert');
        DB::statement('DROP FUNCTION IF EXISTS try_reserve_stock');
    }
};
