-- ============================================================
--  FitSync — Checkout Enhancement Migration
--  /database/checkout_migration.sql
-- ============================================================
SET NAMES utf8mb4;

-- Extend order status enum
ALTER TABLE `orders`
    MODIFY COLUMN `status`
        ENUM('pending','processing','out_for_delivery','delivered',
             'ready_for_pickup','picked_up','cancelled')
        NOT NULL DEFAULT 'pending';

-- Add checkout columns (IF NOT EXISTS guard via PROCEDURE)
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='fulfillment_method');
SET @sql = IF(@col=0,
    'ALTER TABLE `orders`
        ADD COLUMN `fulfillment_method`  ENUM(''delivery'',''pickup'') NOT NULL DEFAULT ''delivery'' AFTER `status`,
        ADD COLUMN `delivery_fee`        DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER `fulfillment_method`,
        ADD COLUMN `delivery_address`    JSON DEFAULT NULL AFTER `delivery_fee`,
        ADD COLUMN `pickup_branch_id`    SMALLINT UNSIGNED DEFAULT NULL AFTER `delivery_address`,
        ADD COLUMN `pickup_date`         DATE DEFAULT NULL AFTER `pickup_branch_id`,
        ADD COLUMN `pickup_time`         VARCHAR(20) DEFAULT NULL AFTER `pickup_date`,
        ADD COLUMN `payment_method`      VARCHAR(30) NOT NULL DEFAULT ''cash'' AFTER `pickup_time`,
        ADD COLUMN `payment_status`      ENUM(''pending'',''paid'',''rejected'') NOT NULL DEFAULT ''pending'' AFTER `payment_method`,
        ADD COLUMN `proof_of_payment`    VARCHAR(255) DEFAULT NULL AFTER `payment_status`,
        ADD COLUMN `order_notes`         TEXT DEFAULT NULL AFTER `proof_of_payment`,
        ADD COLUMN `recipient_name`      VARCHAR(160) DEFAULT NULL AFTER `order_notes`,
        ADD COLUMN `recipient_contact`   VARCHAR(30) DEFAULT NULL AFTER `recipient_name`,
        ADD COLUMN `recipient_email`     VARCHAR(160) DEFAULT NULL AFTER `recipient_contact`',
    'SELECT ''columns already exist'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index: idx_payment_status (guarded for MySQL compatibility)
SET @idx1 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND INDEX_NAME='idx_payment_status');
SET @sql1 = IF(@idx1=0,
    'ALTER TABLE `orders` ADD INDEX `idx_payment_status` (`payment_status`)',
    'SELECT ''idx_payment_status already exists'' AS info');
PREPARE stmt FROM @sql1;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index: idx_fulfillment (guarded for MySQL compatibility)
SET @idx2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND INDEX_NAME='idx_fulfillment');
SET @sql2 = IF(@idx2=0,
    'ALTER TABLE `orders` ADD INDEX `idx_fulfillment` (`fulfillment_method`)',
    'SELECT ''idx_fulfillment already exists'' AS info');
PREPARE stmt FROM @sql2;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;