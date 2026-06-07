-- ============================================================================
-- FreshMart Database Schema
-- ----------------------------------------------------------------------------
-- Database: MySQL 8.0+
-- Engine:   InnoDB (transaction & foreign-key support)
-- Charset:  utf8mb4 (full Unicode support including emoji)
-- Timezone: Asia/Kuala_Lumpur (GMT+8)
-- ============================================================================

DROP DATABASE IF EXISTS freshmart;
CREATE DATABASE freshmart
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE       utf8mb4_unicode_ci;
USE freshmart;

SET time_zone = '+08:00';

-- ============================================================================
-- SECTION 1: AUTH & USER MANAGEMENT (Tables 1-7)
-- ============================================================================

-- 1. users — Core identity for all user types
CREATE TABLE users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,                       -- bcrypt via password_hash()
    role            ENUM('CUSTOMER','RETAILER','ADMIN') NOT NULL DEFAULT 'CUSTOMER',
    status          ENUM('ACTIVE','SUSPENDED','PENDING') NOT NULL DEFAULT 'PENDING',
    email_verified  BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP NULL,
    INDEX idx_users_email  (email),
    INDEX idx_users_role   (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB;

-- 2. retailers — Business-level info for users with RETAILER role
CREATE TABLE retailers (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             BIGINT UNSIGNED NOT NULL UNIQUE,
    company_name        VARCHAR(255) NOT NULL,
    business_reg_no     VARCHAR(50)  NOT NULL UNIQUE,            -- SSM number (Malaysia)
    contact_phone       VARCHAR(20),
    business_address    TEXT,
    approval_status     ENUM('PENDING','APPROVED','REJECTED','SUSPENDED') NOT NULL DEFAULT 'PENDING',
    approved_by         BIGINT UNSIGNED NULL,
    approved_at         TIMESTAMP NULL,
    rejection_reason    TEXT,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_retailers_approval (approval_status)
) ENGINE=InnoDB;

-- 3. profiles — Extended personal info
CREATE TABLE profiles (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL UNIQUE,
    full_name       VARCHAR(255) NOT NULL,
    phone           VARCHAR(20),
    avatar_path     VARCHAR(500),
    date_of_birth   DATE,
    gender          ENUM('M','F','OTHER'),
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. addresses — Multiple shipping/billing addresses per user
CREATE TABLE addresses (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    label           VARCHAR(50)  NOT NULL,                       -- "Home", "Office"
    recipient_name  VARCHAR(255) NOT NULL,
    phone           VARCHAR(20)  NOT NULL,
    line1           VARCHAR(255) NOT NULL,
    line2           VARCHAR(255),
    city            VARCHAR(100) NOT NULL,
    state           VARCHAR(100) NOT NULL,
    postcode        VARCHAR(10)  NOT NULL,
    country         VARCHAR(100) NOT NULL DEFAULT 'Malaysia',
    type            ENUM('SHIPPING','BILLING','BOTH') NOT NULL DEFAULT 'BOTH',
    is_default      BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_addresses_user (user_id)
) ENGINE=InnoDB;

-- 5. sessions — Remember-me tokens & session tracking
CREATE TABLE sessions (
    id              VARCHAR(128) PRIMARY KEY,                    -- session_id
    user_id         BIGINT UNSIGNED NOT NULL,
    ip_address      VARCHAR(45),
    user_agent      VARCHAR(500),
    payload         TEXT,
    last_activity   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sessions_user    (user_id),
    INDEX idx_sessions_expires (expires_at)
) ENGINE=InnoDB;

-- 6. password_resets — Forgot password tokens
CREATE TABLE password_resets (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,
    expires_at  TIMESTAMP NOT NULL,
    used_at     TIMESTAMP NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_resets_token (token_hash)
) ENGINE=InnoDB;

-- 7. email_verifications — Email verification tokens
CREATE TABLE email_verifications (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,
    expires_at  TIMESTAMP NOT NULL,
    verified_at TIMESTAMP NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_verif_token (token_hash)
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 2: PRODUCT CATALOG (Tables 8-12)
-- ============================================================================

-- 8. categories — Top-level taxonomy with shelf-life baseline
CREATE TABLE categories (
    id                          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                        VARCHAR(100) NOT NULL UNIQUE,
    slug                        VARCHAR(120) NOT NULL UNIQUE,
    description                 TEXT,
    icon                        VARCHAR(50),                     -- Lucide icon name
    default_shelf_life_days     INT NOT NULL DEFAULT 7,          -- Used when product doesn't override
    decay_exponent              DECIMAL(3,2) NOT NULL DEFAULT 1.00,  -- Power-law decay (Level 2)
    decay_rationale             VARCHAR(255) NULL,                -- Why this exponent
    display_order               INT NOT NULL DEFAULT 0,
    is_active                   BOOLEAN NOT NULL DEFAULT TRUE,
    created_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_categories_slug (slug)
) ENGINE=InnoDB;

-- 9. subcategories — Nested grouping under categories
CREATE TABLE subcategories (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id     BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,
    slug            VARCHAR(120) NOT NULL,
    display_order   INT NOT NULL DEFAULT 0,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY uq_subcat_slug (category_id, slug)
) ENGINE=InnoDB;

-- 10. unit_types — Measurement units (kg, g, pack, piece, dozen)
CREATE TABLE unit_types (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(20)  NOT NULL UNIQUE,                -- 'kg', 'pack'
    name            VARCHAR(50)  NOT NULL,                       -- 'Kilogram'
    is_weight       BOOLEAN NOT NULL DEFAULT FALSE,
    display_order   INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- 11. products — Core product catalog
CREATE TABLE products (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    retailer_id         BIGINT UNSIGNED NOT NULL,
    category_id         BIGINT UNSIGNED NOT NULL,
    subcategory_id      BIGINT UNSIGNED NULL,
    unit_type_id        BIGINT UNSIGNED NOT NULL,
    sku                 VARCHAR(50)  NOT NULL UNIQUE,
    name                VARCHAR(255) NOT NULL,
    slug                VARCHAR(280) NOT NULL UNIQUE,
    description         TEXT,
    base_price          DECIMAL(10,2) NOT NULL,                  -- MYR price per unit
    shelf_life_days     INT NULL,                                -- Override category default
    decay_exponent_override DECIMAL(3,2) NULL,                   -- Override category decay (Level 2)
    min_order_qty       DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    low_stock_threshold DECIMAL(10,2) NOT NULL DEFAULT 10.00,    -- R-APP-27
    origin              VARCHAR(100),                            -- 'Cameron Highlands'
    storage_instruction TEXT,
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    is_featured         BOOLEAN NOT NULL DEFAULT FALSE,
    view_count          INT UNSIGNED NOT NULL DEFAULT 0,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          TIMESTAMP NULL,
    FOREIGN KEY (retailer_id)    REFERENCES retailers(id)    ON DELETE CASCADE,
    FOREIGN KEY (category_id)    REFERENCES categories(id)   ON DELETE RESTRICT,
    FOREIGN KEY (subcategory_id) REFERENCES subcategories(id) ON DELETE SET NULL,
    FOREIGN KEY (unit_type_id)   REFERENCES unit_types(id)   ON DELETE RESTRICT,
    INDEX idx_products_retailer (retailer_id),
    INDEX idx_products_category (category_id),
    INDEX idx_products_active   (is_active),
    INDEX idx_products_featured (is_featured),
    FULLTEXT idx_products_search (name, description)
) ENGINE=InnoDB;

-- 12. product_images — Up to 5 images per product (max 5MB each, enforced in PHP)
CREATE TABLE product_images (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id      BIGINT UNSIGNED NOT NULL,
    image_path      VARCHAR(500) NOT NULL,
    alt_text        VARCHAR(255),
    is_primary      BOOLEAN NOT NULL DEFAULT FALSE,
    display_order   INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_pimages_product (product_id)
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 3: FEFO & FRESHNESS CORE (Tables 13-16)
-- ============================================================================

-- 13. suppliers — Where retailers source their produce
CREATE TABLE suppliers (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    retailer_id     BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(255) NOT NULL,
    contact_person  VARCHAR(255),
    phone           VARCHAR(20),
    email           VARCHAR(255),
    address         TEXT,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (retailer_id) REFERENCES retailers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 14. stock_batches — THE FEFO CORE TABLE
-- Each row represents a physical batch of stock with its own expiry date.
-- FEFO picks the batch with the earliest expiry_date first.
CREATE TABLE stock_batches (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id              BIGINT UNSIGNED NOT NULL,
    supplier_id             BIGINT UNSIGNED NULL,
    batch_code              VARCHAR(50) NOT NULL,                 -- Human-readable
    received_date           DATE NOT NULL,
    expiry_date             DATE NOT NULL,
    original_quantity       DECIMAL(10,2) NOT NULL,
    quantity_remaining      DECIMAL(10,2) NOT NULL,
    cost_per_unit           DECIMAL(10,2),                        -- Internal cost (retailer only)
    selling_price_override  DECIMAL(10,2) NULL,                   -- Auto-discount for LAST_CHANCE
    storage_location        VARCHAR(100),                         -- 'Shelf A3', 'Cold Room 2'
    status                  ENUM('ACTIVE','DEPLETED','EXPIRED','RECALLED') NOT NULL DEFAULT 'ACTIVE',
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id)  REFERENCES products(id)  ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    INDEX idx_batches_product_expiry (product_id, expiry_date, status),  -- FEFO query optimizer
    INDEX idx_batches_status         (status),
    INDEX idx_batches_expiry         (expiry_date)
) ENGINE=InnoDB;

-- 15. inventory_logs — Audit trail for every stock movement
CREATE TABLE inventory_logs (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_batch_id      BIGINT UNSIGNED NOT NULL,
    user_id             BIGINT UNSIGNED NULL,                    -- Who triggered (NULL = system)
    movement_type       ENUM('RESTOCK','SOLD','EXPIRED','DAMAGED','ADJUSTMENT','RETURNED','RECALLED') NOT NULL,
    quantity_change     DECIMAL(10,2) NOT NULL,                  -- Negative for outflow
    quantity_after      DECIMAL(10,2) NOT NULL,
    related_order_id    BIGINT UNSIGNED NULL,
    reason              TEXT,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stock_batch_id) REFERENCES stock_batches(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)        REFERENCES users(id)         ON DELETE SET NULL,
    INDEX idx_invlog_batch (stock_batch_id),
    INDEX idx_invlog_type  (movement_type),
    INDEX idx_invlog_order (related_order_id)
) ENGINE=InnoDB;

-- 16. freshness_config — Global thresholds for the 4 freshness levels
CREATE TABLE freshness_config (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level_name          ENUM('VERY_FRESH','FRESH','ENJOY_SOON','LAST_CHANCE') NOT NULL UNIQUE,
    min_percent         DECIMAL(5,2) NOT NULL,                   -- % shelf life remaining
    max_percent         DECIMAL(5,2) NOT NULL,
    color_hex           VARCHAR(7)   NOT NULL,                   -- '#16a34a'
    label_en            VARCHAR(50)  NOT NULL,
    auto_discount_pct   DECIMAL(5,2) NOT NULL DEFAULT 0.00,      -- 15.00 for LAST_CHANCE
    alert_retailer      BOOLEAN NOT NULL DEFAULT FALSE,          -- Send notification?
    display_order       INT NOT NULL DEFAULT 0,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 4: SHOPPING (Tables 17-20)
-- ============================================================================

-- 17. carts — Cart per user (or guest session)
CREATE TABLE carts (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             BIGINT UNSIGNED NULL,                    -- NULL = guest cart
    guest_session_id    VARCHAR(128) NULL,                       -- For 24hr guest carts
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at          TIMESTAMP NULL,                          -- Guest cart expiry
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_carts_user  (user_id),
    INDEX idx_carts_guest (guest_session_id)
) ENGINE=InnoDB;

-- 18. cart_items
CREATE TABLE cart_items (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cart_id         BIGINT UNSIGNED NOT NULL,
    product_id      BIGINT UNSIGNED NOT NULL,
    quantity        DECIMAL(10,2) NOT NULL,
    price_snapshot  DECIMAL(10,2) NOT NULL,                      -- Price at time of adding
    added_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cart_id)    REFERENCES carts(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY uq_cart_product (cart_id, product_id)
) ENGINE=InnoDB;

-- 19. wishlists
CREATE TABLE wishlists (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL UNIQUE,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 20. wishlist_items
CREATE TABLE wishlist_items (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wishlist_id BIGINT UNSIGNED NOT NULL,
    product_id  BIGINT UNSIGNED NOT NULL,
    added_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wishlist_id) REFERENCES wishlists(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id)  REFERENCES products(id)  ON DELETE CASCADE,
    UNIQUE KEY uq_wishlist_product (wishlist_id, product_id)
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 5: ORDERS & TRANSACTIONS (Tables 21-25)
-- ============================================================================

-- 21. orders
CREATE TABLE orders (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number        VARCHAR(30)  NOT NULL UNIQUE,            -- 'FM-20260519-0001'
    user_id             BIGINT UNSIGNED NOT NULL,
    shipping_address_id BIGINT UNSIGNED NOT NULL,
    preferred_delivery_date DATE NULL,                           -- R-APP-17 delivery day
    billing_address_id  BIGINT UNSIGNED NULL,
    promo_code_id       BIGINT UNSIGNED NULL,
    subtotal            DECIMAL(10,2) NOT NULL,
    discount_amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping_fee        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_amount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total               DECIMAL(10,2) NOT NULL,
    status              ENUM('PLACED','PROCESSING','QUALITY_CHECK','PACKED','OUT_FOR_DELIVERY','DELIVERED','CANCELLED','REFUNDED') NOT NULL DEFAULT 'PLACED',
    notes               TEXT,
    placed_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)             REFERENCES users(id)     ON DELETE RESTRICT,
    FOREIGN KEY (shipping_address_id) REFERENCES addresses(id) ON DELETE RESTRICT,
    FOREIGN KEY (billing_address_id)  REFERENCES addresses(id) ON DELETE SET NULL,
    INDEX idx_orders_user   (user_id),
    INDEX idx_orders_status (status),
    INDEX idx_orders_placed (placed_at)
) ENGINE=InnoDB;

-- 22. order_items — Snapshot of products + which batch they came from (FEFO)
CREATE TABLE order_items (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id            BIGINT UNSIGNED NOT NULL,
    product_id          BIGINT UNSIGNED NOT NULL,
    stock_batch_id      BIGINT UNSIGNED NOT NULL,                -- Which batch fulfilled this
    product_name        VARCHAR(255)  NOT NULL,                  -- Snapshot
    quantity            DECIMAL(10,2) NOT NULL,
    unit_price          DECIMAL(10,2) NOT NULL,
    subtotal            DECIMAL(10,2) NOT NULL,
    freshness_at_order  ENUM('VERY_FRESH','FRESH','ENJOY_SOON','LAST_CHANCE') NOT NULL,
    expiry_at_order     DATE NOT NULL,                           -- Snapshot
    FOREIGN KEY (order_id)       REFERENCES orders(id)        ON DELETE CASCADE,
    FOREIGN KEY (product_id)     REFERENCES products(id)      ON DELETE RESTRICT,
    FOREIGN KEY (stock_batch_id) REFERENCES stock_batches(id) ON DELETE RESTRICT,
    INDEX idx_oitems_order (order_id)
) ENGINE=InnoDB;

-- 23. order_history — Status transitions
CREATE TABLE order_history (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id        BIGINT UNSIGNED NOT NULL,
    previous_status VARCHAR(50),
    new_status      VARCHAR(50) NOT NULL,
    changed_by      BIGINT UNSIGNED NULL,
    notes           TEXT,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id)  ON DELETE SET NULL,
    INDEX idx_ohistory_order (order_id)
) ENGINE=InnoDB;

-- 24. payments — Simulated (no real gateway)
CREATE TABLE payments (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id            BIGINT UNSIGNED NOT NULL,
    payment_method      ENUM('FPX','CREDIT_CARD','EWALLET','BANK_TRANSFER','COD') NOT NULL,
    amount              DECIMAL(10,2) NOT NULL,
    status              ENUM('PENDING','SUCCESS','FAILED','REFUNDED') NOT NULL DEFAULT 'PENDING',
    transaction_ref     VARCHAR(100),                            -- Mock reference
    paid_at             TIMESTAMP NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_payments_order (order_id)
) ENGINE=InnoDB;

-- 25. shipments — Simulated delivery tracking
CREATE TABLE shipments (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id            BIGINT UNSIGNED NOT NULL UNIQUE,
    tracking_number     VARCHAR(50)  NOT NULL UNIQUE,
    carrier             VARCHAR(50)  NOT NULL DEFAULT 'FreshMart Express',
    estimated_delivery  DATE,
    shipped_at          TIMESTAMP NULL,
    delivered_at        TIMESTAMP NULL,
    notes               TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 6: MARKETING (Tables 26-27)
-- ============================================================================

-- 26. promo_codes
CREATE TABLE promo_codes (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(50)  NOT NULL UNIQUE,
    description     TEXT,
    discount_type   ENUM('PERCENTAGE','FIXED_AMOUNT') NOT NULL,
    discount_value  DECIMAL(10,2) NOT NULL,                      -- 10.00 = 10% or RM10
    min_order_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    max_discount    DECIMAL(10,2) NULL,                          -- Cap (for percentage)
    usage_limit     INT NULL,                                    -- NULL = unlimited
    usage_count     INT NOT NULL DEFAULT 0,
    user_limit      INT NOT NULL DEFAULT 1,                      -- Per-user usage
    starts_at       TIMESTAMP NOT NULL,
    expires_at      TIMESTAMP NOT NULL,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    created_by      BIGINT UNSIGNED NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_promos_code (code),
    INDEX idx_promos_dates (starts_at, expires_at)
) ENGINE=InnoDB;

-- 27. promo_code_usages — Track who used what, when
CREATE TABLE promo_code_usages (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    promo_code_id   BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    order_id        BIGINT UNSIGNED NOT NULL UNIQUE,
    discount_amount DECIMAL(10,2) NOT NULL,
    used_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (promo_code_id) REFERENCES promo_codes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)       REFERENCES users(id)       ON DELETE RESTRICT,
    FOREIGN KEY (order_id)      REFERENCES orders(id)      ON DELETE CASCADE,
    INDEX idx_promousage_promo (promo_code_id),
    INDEX idx_promousage_user  (user_id)
) ENGINE=InnoDB;

-- ============================================================================
-- SECTION 7: FEEDBACK & SYSTEM (Tables 28-33)
-- ============================================================================

-- 28. reviews — Verified purchase only
CREATE TABLE reviews (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    product_id      BIGINT UNSIGNED NOT NULL,
    order_id        BIGINT UNSIGNED NOT NULL,                    -- Proof of purchase
    rating          TINYINT UNSIGNED NOT NULL,                   -- 1-5
    title           VARCHAR(255),
    body            TEXT,
    is_approved     BOOLEAN NOT NULL DEFAULT TRUE,               -- Admin moderation
    helpful_count   INT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE RESTRICT,
    UNIQUE KEY uq_review_user_product_order (user_id, product_id, order_id),
    INDEX idx_reviews_product (product_id),
    CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- 29. review_replies — Retailer responses
CREATE TABLE review_replies (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    review_id   BIGINT UNSIGNED NOT NULL UNIQUE,
    retailer_id BIGINT UNSIGNED NOT NULL,
    body        TEXT NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id)   REFERENCES reviews(id)   ON DELETE CASCADE,
    FOREIGN KEY (retailer_id) REFERENCES retailers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 30. notifications — In-app alerts
CREATE TABLE notifications (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    type        ENUM('ORDER_UPDATE','EXPIRY_ALERT','APPROVAL','REVIEW_REPLY','PROMO','SYSTEM') NOT NULL,
    title       VARCHAR(255) NOT NULL,
    body        TEXT,
    link        VARCHAR(500),                                    -- Where to go on click
    is_read     BOOLEAN NOT NULL DEFAULT FALSE,
    read_at     TIMESTAMP NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user_unread (user_id, is_read),
    INDEX idx_notif_created (created_at)
) ENGINE=InnoDB;

-- 31. audit_logs — Admin-level sensitive action tracking
CREATE TABLE audit_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NULL,                        -- Who did it
    action          VARCHAR(100) NOT NULL,                       -- 'USER_SUSPEND', 'PROMO_CREATE'
    entity_type     VARCHAR(50),                                 -- 'user', 'order'
    entity_id       BIGINT UNSIGNED,
    old_values      JSON,
    new_values      JSON,
    ip_address      VARCHAR(45),
    user_agent      VARCHAR(500),
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_user   (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB;

-- 32. platform_statistics — Daily aggregates for analytics charts
CREATE TABLE platform_statistics (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stat_date               DATE NOT NULL UNIQUE,
    total_orders            INT UNSIGNED NOT NULL DEFAULT 0,
    total_revenue           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_customers_active  INT UNSIGNED NOT NULL DEFAULT 0,
    new_signups             INT UNSIGNED NOT NULL DEFAULT 0,
    products_expired        INT UNSIGNED NOT NULL DEFAULT 0,
    products_last_chance    INT UNSIGNED NOT NULL DEFAULT 0,
    waste_kg_saved          DECIMAL(10,2) NOT NULL DEFAULT 0.00, -- KPI for sustainability
    avg_order_value         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stats_date (stat_date)
) ENGINE=InnoDB;

-- 33. system_config — Platform-wide settings
CREATE TABLE system_config (
    config_key      VARCHAR(100) PRIMARY KEY,
    config_value    TEXT NOT NULL,
    description     TEXT,
    updated_by      BIGINT UNSIGNED NULL,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================================
-- END OF SCHEMA — 33 TABLES CREATED
-- ============================================================================
