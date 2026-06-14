-- ============================================================
--  FitSync — Shop Module Schema
--  /database/shop_schema.sql
--
--  Run once against the `fitsync` database:
--    mysql -u root fitsync < database/shop_schema.sql
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ── 1. PRODUCTS ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `products` (
    `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(160)    NOT NULL,
    `description` TEXT            NOT NULL,
    `category`    VARCHAR(80)     NOT NULL DEFAULT 'Supplement',
    `price`       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `stock`       INT             NOT NULL DEFAULT 0,
    `image`       VARCHAR(255)    NOT NULL DEFAULT '',
    `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_category` (`category`),
    KEY `idx_active`   (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. CART ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `cart` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity`   INT          NOT NULL DEFAULT 1,
    `added_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_product` (`user_id`, `product_id`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. ORDERS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `orders` (
    `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `user_id`      INT UNSIGNED   NOT NULL,
    `total_amount` DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `status`       ENUM('pending','processing','shipped','delivered','cancelled')
                                  NOT NULL DEFAULT 'pending',
    `created_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. ORDER ITEMS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_items` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`   INT UNSIGNED  NOT NULL,
    `product_id` INT UNSIGNED  NOT NULL,
    `quantity`   INT           NOT NULL DEFAULT 1,
    `price`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`id`),
    KEY `idx_order_id`   (`order_id`),
    KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── SAMPLE PRODUCTS ──────────────────────────────────────────
INSERT IGNORE INTO `products` (`id`, `name`, `description`, `category`, `price`, `stock`, `image`) VALUES
(1,  'Whey Protein',          'Premium whey protein isolate with 25g of protein per serving. Fast-absorbing formula ideal for post-workout recovery. Available in Chocolate, Vanilla, and Strawberry flavors.',              'Supplement',  1499.00, 50,  'uploads/products/whey_protein.jpg'),
(2,  'Creatine Monohydrate',  'Pure micronized creatine monohydrate. Proven to increase strength, power output, and muscle volume. Unflavored — mixes easily with any drink. 300g per container.',                        'Supplement',   699.00, 75,  'uploads/products/creatine.jpg'),
(3,  'Mass Gainer',           'High-calorie mass gainer with 50g protein and 250g carbs per serving. Designed for hardgainers who struggle to meet caloric goals. Rich chocolate flavor with easy mixability.',            'Supplement',  1899.00, 30,  'uploads/products/mass_gainer.jpg'),
(4,  'Pre-Workout',           'High-stim pre-workout formula with 300mg caffeine, beta-alanine, and L-citrulline. Explosive energy, intense focus, and skin-splitting pumps. 30 servings.',                               'Supplement',   999.00, 40,  'uploads/products/pre_workout.jpg'),
(5,  'BCAA',                  'Branched-chain amino acids in a 2:1:1 ratio (Leucine, Isoleucine, Valine). Reduces muscle breakdown during training and accelerates recovery. Refreshing fruit punch flavor.',             'Supplement',   799.00, 60,  'uploads/products/bcaa.jpg'),
(6,  'Multivitamins',         'Complete daily multivitamin formula with 23 essential vitamins and minerals. Supports immune function, energy metabolism, and overall health. 90 tablets per bottle.',                       'Supplement',   549.00, 80,  'uploads/products/multivitamins.jpg'),
(7,  'Fish Oil',              'High-potency omega-3 fish oil with 1000mg EPA and DHA per softgel. Supports heart health, joint mobility, and cognitive function. Enteric-coated to prevent fishy aftertaste.',            'Supplement',   449.00, 90,  'uploads/products/fish_oil.jpg'),
(8,  'Shaker Bottle',         'BPA-free 700ml shaker bottle with stainless steel mixing ball. Leak-proof lid, measurement markings, and a wide mouth for easy cleaning. Available in Black and White.',                   'Equipment',    299.00, 35,  'uploads/products/shaker_bottle.jpg'),
(9,  'Gym Towel',             'Microfiber gym towel with fast-drying technology. Ultra-absorbent, lightweight, and compact. FitSync logo embroidered on corner. 40x80cm — perfect gym bag size.',                        'Equipment',    249.00, 45,  'uploads/products/gym_towel.jpg'),
(10, 'Resistance Bands',      'Set of 5 resistance bands in varying tensions (5–50 lbs). Made from premium latex for durability. Ideal for warm-ups, mobility work, and accessory exercises. Includes carry pouch.',     'Equipment',    599.00, 25,  'uploads/products/resistance_bands.jpg'),
(11, 'Lifting Straps',        'Heavy-duty cotton lifting straps with neoprene wrist padding. Improves grip during deadlifts, rows, and shrugs. Sold as a pair. Adjustable length fits all wrist sizes.',                 'Equipment',    349.00, 55,  'uploads/products/lifting_straps.jpg');
