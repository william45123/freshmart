-- ============================================================================
-- FreshMart Seed Data — EXPANDED EDITION
-- Run AFTER schema.sql
-- ============================================================================
-- 35 products across all 8 categories (>=4 per category)
-- Stock batches deliberately span all 4 freshness levels for demo purposes
-- ============================================================================
USE freshmart;
SET time_zone = '+08:00';

-- ============================================================================
-- 1. Unit Types
-- ============================================================================
INSERT INTO unit_types (code, name, is_weight, display_order) VALUES
('kg',     'Kilogram',  TRUE,  1),
('g',      'Gram',      TRUE,  2),
('pack',   'Pack',      FALSE, 3),
('piece',  'Piece',     FALSE, 4),
('bunch',  'Bunch',     FALSE, 5),
('dozen',  'Dozen',     FALSE, 6),
('box',    'Box',       FALSE, 7),
('bottle', 'Bottle',    FALSE, 8),
('loaf',   'Loaf',      FALSE, 9);

-- ============================================================================
-- 2. Freshness Configuration
-- ============================================================================
INSERT INTO freshness_config
    (level_name, min_percent, max_percent, color_hex, label_en, auto_discount_pct, alert_retailer, display_order)
VALUES
    ('VERY_FRESH',  75.00, 100.00, '#4a5a3a', 'Very Fresh',   0.00, FALSE, 1),
    ('FRESH',       50.00,  74.99, '#7a8467', 'Fresh',        0.00, FALSE, 2),
    ('ENJOY_SOON',  25.00,  49.99, '#c9a55a', 'Enjoy Soon',   0.00, TRUE,  3),
    ('LAST_CHANCE',  0.01,  24.99, '#b85c38', 'Last Chance', 15.00, TRUE,  4);

-- ============================================================================
-- 3. Categories — 8 categories with food-science-grounded power-law exponents
-- ============================================================================
INSERT INTO categories (name, slug, description, icon, default_shelf_life_days, decay_exponent, decay_rationale, display_order) VALUES
('Vegetables',   'vegetables',   'Fresh local and imported vegetables',     'carrot',     7,  1.50, 'Mixed transpiration + respiration',                  1),
('Fruits',       'fruits',       'Seasonal Malaysian and imported fruits',  'apple',      10, 1.10, 'Slow respiration; hardy varieties last longer',       2),
('Dairy',        'dairy',        'Milk, yoghurt, cheese, and butter',       'milk',       14, 1.30, 'Refrigerated; microbial growth controlled',           3),
('Meat',         'meat',         'Fresh halal chicken, beef, and lamb',     'beef',       5,  2.30, 'Microbial growth + lipid oxidation',                  4),
('Seafood',      'seafood',      'Fresh and frozen fish and shellfish',     'fish',       4,  2.50, 'Rapid bacterial growth + trimethylamine accumulation',5),
('Bakery',       'bakery',       'Fresh bread, pastries, and baked goods',  'cookie',     3,  2.00, 'Staling follows Avrami kinetics; texture degrades fast', 6),
('Eggs & Tofu',  'eggs-tofu',    'Eggs, tofu, and soy products',            'egg',        14, 1.00, 'Near-linear decay under refrigeration',               7),
('Herbs & Spice','herbs-spice',  'Fresh herbs, ginger, garlic, chillies',   'leaf',       10, 1.80, 'Wilting via transpiration is exponential',            8);

-- ============================================================================
-- 4. Subcategories
-- ============================================================================
INSERT INTO subcategories (category_id, name, slug, display_order) VALUES
(1, 'Leafy Greens',   'leafy-greens',   1),
(1, 'Root Vegetables','root-vegetables',2),
(1, 'Fruiting Veg',   'fruiting-veg',   3),
(2, 'Tropical',       'tropical',       1),
(2, 'Imported',       'imported',       2),
(3, 'Milk',           'milk',           1),
(3, 'Cheese',         'cheese',         2),
(3, 'Yogurt',         'yogurt',         3),
(4, 'Chicken',        'chicken',        1),
(4, 'Beef',           'beef',           2),
(5, 'Fish',           'fish',           1),
(5, 'Shellfish',      'shellfish',      2),
(6, 'Bread',          'bread',          1),
(6, 'Pastries',       'pastries',       2),
(8, 'Fresh Herbs',    'fresh-herbs',    1),
(8, 'Aromatics',      'aromatics',      2);

-- ============================================================================
-- 5. Test users (bcrypt hashes for the 3 demo passwords)
-- admin@freshmart.my / Admin@123
-- retailer@cameron.my / Retailer@123
-- cherry@example.my / Customer@123
-- ============================================================================
INSERT INTO users (email, password_hash, role, status, email_verified) VALUES
('admin@freshmart.my',      '$2y$10$L8jPLwGmL/8WTXrm0xJqjOq9hi5lEKMx55fNCG2vNu5MFGsR.Ph5G', 'ADMIN',    'ACTIVE', TRUE),
('retailer@cameron.my',     '$2y$10$RWuxJZyxoFsXcGmCl2XxLeFFRVa9bcyTtPJZF7xqAjxR7Ek5MJcKi', 'RETAILER', 'ACTIVE', TRUE),
('cherry@example.my',       '$2y$10$ASZbKgqfa57P12g/sCdwzudG1H7G3owM5Mfm.KSXLqxz9Y6IXopBy', 'CUSTOMER', 'ACTIVE', TRUE);

SET @admin_id    = (SELECT id FROM users WHERE email = 'admin@freshmart.my');
SET @retailer_user_id = (SELECT id FROM users WHERE email = 'retailer@cameron.my');
SET @customer_id = (SELECT id FROM users WHERE email = 'cherry@example.my');

INSERT INTO profiles (user_id, full_name, phone) VALUES
(@admin_id,           'Platform Administrator', '+60123456789'),
(@retailer_user_id,   'Lim Wei Ming',           '+60195551234'),
(@customer_id,        'Cherry Tan',             '+60123334444');

INSERT INTO retailers (user_id, company_name, business_reg_no, contact_phone, business_address, approval_status, approved_by, approved_at) VALUES
(@retailer_user_id, 'Cameron Fresh Sdn Bhd', '202301012345', '+60195551234',
 'Lot 42, Cameron Highlands, Pahang 39000', 'APPROVED', @admin_id, NOW());

SET @retailer_id = LAST_INSERT_ID();

INSERT INTO addresses (user_id, label, recipient_name, phone, line1, city, state, postcode, is_default) VALUES
(@customer_id, 'Home', 'Cherry Tan', '+60123334444',
 'Block A-3-12, Cyberia SmartHomes', 'Cyberjaya', 'Selangor', '63000', TRUE);

-- ============================================================================
-- 6. Suppliers
-- ============================================================================
INSERT INTO suppliers (retailer_id, name, contact_person, phone, address, is_active) VALUES
(@retailer_id, 'Highland Farms Co-op', 'Ahmad Bin Hassan', '+60195551234',
 'Tringkap, Cameron Highlands, Pahang', TRUE);

SET @supplier_id = LAST_INSERT_ID();

-- ============================================================================
-- 7. PRODUCTS — 35 items spanning all 8 categories
-- ============================================================================
INSERT INTO products
    (retailer_id, category_id, subcategory_id, unit_type_id, sku, name, slug,
     description, base_price, shelf_life_days, origin, storage_instruction, is_active, is_featured)
VALUES
-- VEGETABLES
(@retailer_id, 1, 1, 1, 'VEG-LET-001', 'Butterhead Lettuce',     'butterhead-lettuce',
 'Crisp local butterhead lettuce, ideal for salads and wraps. Grown pesticide-free in Cameron Highlands.',
 4.90, 5, 'Cameron Highlands', 'Refrigerate at 1-4°C. Keep in plastic bag.', TRUE, TRUE),

(@retailer_id, 1, 1, 1, 'VEG-BOK-002', 'Baby Bok Choy',          'baby-bok-choy',
 'Tender baby bok choy, perfect for stir-frying with garlic. Local highland variety.',
 3.50, 5, 'Cameron Highlands', 'Refrigerate. Wash before use.', TRUE, FALSE),

(@retailer_id, 1, 3, 1, 'VEG-TOM-003', 'Cherry Tomatoes',        'cherry-tomatoes',
 'Sweet cherry tomatoes, vine-ripened. Great for salads and snacking.',
 12.90, 7, 'Cameron Highlands', 'Store at room temperature until ripe.', TRUE, TRUE),

(@retailer_id, 1, 2, 1, 'VEG-CAR-004', 'Cameron Carrots',        'cameron-carrots',
 'Sweet orange carrots straight from Cameron Highlands. Earthy flavour, crunchy texture.',
 5.50, 14, 'Cameron Highlands', 'Refrigerate in crisper drawer.', TRUE, FALSE),

(@retailer_id, 1, 1, 1, 'VEG-SPI-005', 'Baby Spinach',           'baby-spinach',
 'Tender young spinach leaves, washed and ready to eat.',
 6.90, 5, 'Cameron Highlands', 'Refrigerate. Use within 5 days.', TRUE, FALSE),

-- FRUITS
(@retailer_id, 2, 4, 1, 'FRU-MAN-001', 'Harum Manis Mango',      'harum-manis-mango',
 'Sweet Malaysian Harum Manis mangoes. Aromatic with smooth, fibre-free flesh.',
 18.90, 7, 'Perlis', 'Ripen at room temperature, refrigerate when ripe.', TRUE, TRUE),

(@retailer_id, 2, 5, 1, 'FRU-APP-002', 'Royal Gala Apples',      'royal-gala-apples',
 'Sweet and crispy Royal Gala apples imported from New Zealand.',
 9.90, 21, 'New Zealand', 'Refrigerate for longer shelf life.', TRUE, FALSE),

(@retailer_id, 2, 4, 1, 'FRU-BAN-003', 'Pisang Berangan',        'pisang-berangan',
 'Sweet local bananas, perfect for snacking or smoothies.',
 6.50, 7, 'Pahang', 'Store at room temperature.', TRUE, TRUE),

-- DAIRY
(@retailer_id, 3, 6, 8, 'DAI-MLK-001', 'Fresh Full Cream Milk 1L', 'fresh-full-cream-milk-1l',
 'Locally produced fresh full cream milk from grass-fed cows.',
 7.50, 10, 'Selangor', 'Keep refrigerated below 4°C.', TRUE, TRUE),

(@retailer_id, 3, 8, 3, 'DAI-YOG-002', 'Greek Yogurt 500g',      'greek-yogurt-500g',
 'Thick and creamy Greek-style yogurt, plain unsweetened.',
 12.90, 14, 'Selangor', 'Keep refrigerated. Best before printed date.', TRUE, FALSE),

-- MEAT
(@retailer_id, 4, 9, 1, 'MET-CKB-001', 'Free-Range Chicken Breast', 'free-range-chicken-breast',
 'Halal-certified free-range chicken breast. No hormones, no antibiotics.',
 22.90, 5, 'Johor', 'Keep refrigerated below 4°C. Cook within 5 days.', TRUE, TRUE),

(@retailer_id, 4, 10, 1, 'MET-BEF-002', 'Australian Beef Striploin', 'australian-beef-striploin',
 'Premium Australian beef striploin, marbled and tender.',
 65.00, 7, 'Australia', 'Keep refrigerated below 4°C. Freeze if not used within 5 days.', TRUE, FALSE),

-- SEAFOOD
(@retailer_id, 5, 11, 1, 'SEA-SAL-001', 'Atlantic Salmon Fillet', 'atlantic-salmon-fillet',
 'Fresh Norwegian Atlantic salmon fillet, skin-on, pin-bones removed.',
 88.00, 3, 'Norway', 'Keep refrigerated on ice. Use within 3 days.', TRUE, TRUE),

(@retailer_id, 5, 12, 1, 'SEA-PRA-002', 'Tiger Prawns (Medium)',  'tiger-prawns-medium',
 'Fresh local tiger prawns, head-on. Sweet and meaty.',
 48.00, 3, 'Sabah', 'Keep on ice. Cook within 2 days.', TRUE, FALSE),

-- BAKERY
(@retailer_id, 6, 13, 9, 'BAK-SDB-001', 'Artisan Sourdough Loaf', 'artisan-sourdough-loaf',
 'Slow-fermented sourdough with crisp crust and chewy interior. Baked daily.',
 15.90, 4, 'Selangor', 'Store in paper bag at room temperature.', TRUE, TRUE),

(@retailer_id, 6, 14, 3, 'BAK-CRO-002', 'Butter Croissants (4 pcs)', 'butter-croissants-4pcs',
 'Buttery, flaky French-style croissants made with imported butter.',
 16.50, 3, 'Selangor', 'Best eaten same day. Refresh in oven before serving.', TRUE, FALSE),

-- EGGS & TOFU
(@retailer_id, 7, NULL, 6, 'EGG-CHK-001', 'Grade A Chicken Eggs (Dozen)', 'grade-a-chicken-eggs',
 'Fresh Grade A chicken eggs from local farms.',
 13.90, 21, 'Johor', 'Refrigerate immediately. Use within 3 weeks.', TRUE, FALSE),

-- HERBS & SPICE
(@retailer_id, 8, 15, 5, 'HRB-COR-001', 'Fresh Coriander (Bunch)', 'fresh-coriander-bunch',
 'Bright, aromatic fresh coriander leaves. Perfect for curries and garnish.',
 2.50, 5, 'Cameron Highlands', 'Refrigerate with stems in water.', TRUE, FALSE),

(@retailer_id, 8, 16, 2, 'HRB-GAR-002', 'Local Garlic (250g)',    'local-garlic-250g',
 'Pungent fresh garlic bulbs. Essential aromatic for Malaysian cooking.',
 8.90, 30, 'Cameron Highlands', 'Store in cool dry place.', TRUE, TRUE),

-- ============================================================================
-- ADDITIONAL VEGETABLES
-- ============================================================================
(@retailer_id, 1, 2, 1, 'VEG-POT-006', 'Cameron Potatoes',       'cameron-potatoes',
 'Locally grown potatoes from Cameron Highlands. All-purpose variety.',
 6.50, 21, 'Cameron Highlands', 'Store in cool, dark place.', TRUE, FALSE),

(@retailer_id, 1, 3, 1, 'VEG-CUC-007', 'Japanese Cucumber',      'japanese-cucumber',
 'Crisp, mild Japanese cucumbers. Great for salads.',
 7.90, 10, 'Cameron Highlands', 'Refrigerate in plastic bag.', TRUE, FALSE),

-- ADDITIONAL FRUITS
(@retailer_id, 2, 5, 4, 'FRU-DRG-004', 'Honeydew Melon',         'honeydew-melon',
 'Sweet, juicy honeydew melon. Refreshing and naturally hydrating.',
 14.90, 10, 'Australia', 'Refrigerate after cutting.', TRUE, FALSE),

-- ADDITIONAL DAIRY
(@retailer_id, 3, 7, 3, 'DAI-CHE-003', 'Cheddar Cheese Block 250g', 'cheddar-cheese-block',
 'Aged cheddar with rich, sharp flavour.',
 24.90, 30, 'New Zealand', 'Refrigerate. Wrap tightly after opening.', TRUE, FALSE),

(@retailer_id, 3, 6, 8, 'DAI-MLK-004', 'Low Fat Milk 1L',        'low-fat-milk',
 'Fresh pasteurised low-fat milk. Same taste, less fat.',
 6.90, 7, 'Selangor', 'Keep refrigerated below 4 degrees.', TRUE, FALSE),

-- ADDITIONAL MEAT
(@retailer_id, 4, 9, 1, 'MET-CKT-003', 'Free-Range Chicken Thigh', 'free-range-chicken-thigh',
 'Juicy free-range chicken thigh, perfect for grilling and stewing.',
 18.90, 4, 'Johor', 'Refrigerate. Cook within 2 days.', TRUE, FALSE),

(@retailer_id, 4, 10, 3, 'MET-BEF-004', 'Beef Minced 500g',      'beef-minced',
 'Fresh Australian beef minced, ideal for bolognese and burgers.',
 28.90, 3, 'Australia', 'Refrigerate. Cook thoroughly.', TRUE, FALSE),

-- ADDITIONAL SEAFOOD
(@retailer_id, 5, 11, 1, 'SEA-MAC-003', 'Fresh Mackerel',        'fresh-mackerel',
 'Whole fresh mackerel from local waters. Rich in omega-3.',
 18.50, 2, 'Pulau Pangkor', 'Keep on ice. Cook same day.', TRUE, FALSE),

(@retailer_id, 5, 12, 1, 'SEA-SQD-004', 'Squid (Sotong)',        'squid-sotong',
 'Fresh squid, perfect for stir-fries and grilling.',
 32.00, 2, 'Terengganu', 'Keep refrigerated. Clean before cooking.', TRUE, FALSE),

-- ADDITIONAL BAKERY
(@retailer_id, 6, 13, 9, 'BAK-WHT-003', 'Whole Wheat Bread',     'whole-wheat-bread',
 'Hearty whole wheat loaf with seeds. High in fibre.',
 9.90, 5, 'Selangor', 'Store in cool, dry place.', TRUE, FALSE),

(@retailer_id, 6, 14, 3, 'BAK-MUF-004', 'Blueberry Muffins (4 pcs)', 'blueberry-muffins',
 'Soft and fluffy muffins bursting with real blueberries.',
 14.90, 4, 'Selangor', 'Store at room temperature. Best within 2 days.', TRUE, FALSE),

-- ADDITIONAL EGGS & TOFU
(@retailer_id, 7, NULL, 6, 'EGG-OMG-002', 'Omega-3 Chicken Eggs (Dozen)', 'omega-3-chicken-eggs',
 'Premium omega-3 enriched eggs from grain-fed hens.',
 17.90, 21, 'Johor', 'Refrigerate immediately.', TRUE, FALSE),

(@retailer_id, 7, NULL, 3, 'TOF-SLK-003', 'Silken Tofu (300g)',  'silken-tofu',
 'Smooth, custard-like silken tofu.',
 3.50, 14, 'Selangor', 'Keep refrigerated.', TRUE, FALSE),

(@retailer_id, 7, NULL, 3, 'TOF-FRM-004', 'Firm Tofu (400g)',    'firm-tofu',
 'Firm tofu perfect for stir-fries, grilling, and curries.',
 4.20, 14, 'Selangor', 'Keep refrigerated.', TRUE, FALSE),

-- ADDITIONAL HERBS & SPICE
(@retailer_id, 8, 16, 3, 'HRB-GIN-003', 'Fresh Ginger (200g)',   'fresh-ginger',
 'Aromatic fresh ginger root for cooking and tea.',
 6.90, 21, 'Cameron Highlands', 'Store in dry place.', TRUE, FALSE),

(@retailer_id, 8, 15, 5, 'HRB-LMG-004', 'Lemongrass (Serai)',    'lemongrass-serai',
 'Fresh lemongrass stalks. Essential for Malaysian curries and tom yam.',
 3.50, 14, 'Cameron Highlands', 'Refrigerate or freeze.', TRUE, FALSE);

-- ============================================================================
-- 8. STOCK BATCHES — Demo batches across all freshness levels
-- ============================================================================
SET @today = CURDATE();

INSERT INTO stock_batches
    (product_id, supplier_id, batch_code, received_date, expiry_date,
     original_quantity, quantity_remaining, cost_per_unit, storage_location, status)
VALUES
(1,  @supplier_id, 'LET-B001', @today,                              DATE_ADD(@today, INTERVAL 5 DAY),
 50.00, 50.00, 3.00, 'Cold Room 1', 'ACTIVE'),

(2,  @supplier_id, 'BOK-B001', DATE_SUB(@today, INTERVAL 1 DAY),    DATE_ADD(@today, INTERVAL 4 DAY),
 40.00, 35.00, 2.50, 'Cold Room 1', 'ACTIVE'),

(3,  @supplier_id, 'TOM-B001', DATE_SUB(@today, INTERVAL 4 DAY),    DATE_ADD(@today, INTERVAL 3 DAY),
 20.00, 18.00, 9.00, 'Shelf A2', 'ACTIVE'),

(4,  @supplier_id, 'CAR-B001', @today,                              DATE_ADD(@today, INTERVAL 14 DAY),
 60.00, 60.00, 4.00, 'Cold Room 1', 'ACTIVE'),

(5,  @supplier_id, 'SPI-B001', DATE_SUB(@today, INTERVAL 1 DAY),    DATE_ADD(@today, INTERVAL 4 DAY),
 30.00, 28.00, 5.00, 'Cold Room 1', 'ACTIVE'),

(6,  @supplier_id, 'MAN-B001', DATE_SUB(@today, INTERVAL 6 DAY),    DATE_ADD(@today, INTERVAL 1 DAY),
 30.00, 25.00, 13.00, 'Shelf B1', 'ACTIVE'),

(7,  @supplier_id, 'APP-B001', @today,                              DATE_ADD(@today, INTERVAL 21 DAY),
 100.00, 100.00, 7.00, 'Cold Room 2', 'ACTIVE'),

(8,  @supplier_id, 'BAN-B001', DATE_SUB(@today, INTERVAL 2 DAY),    DATE_ADD(@today, INTERVAL 5 DAY),
 40.00, 35.00, 4.50, 'Shelf B2', 'ACTIVE'),

(9,  @supplier_id, 'MLK-B001', DATE_SUB(@today, INTERVAL 2 DAY),    DATE_ADD(@today, INTERVAL 8 DAY),
 60.00, 55.00, 5.50, 'Cold Room 1', 'ACTIVE'),

(10, @supplier_id, 'YOG-B001', @today,                              DATE_ADD(@today, INTERVAL 14 DAY),
 40.00, 40.00, 9.50, 'Cold Room 1', 'ACTIVE'),

(11, @supplier_id, 'CKB-B001', @today,                              DATE_ADD(@today, INTERVAL 5 DAY),
 25.00, 25.00, 16.00, 'Cold Room 3', 'ACTIVE'),

(12, @supplier_id, 'BEF-B001', DATE_SUB(@today, INTERVAL 2 DAY),    DATE_ADD(@today, INTERVAL 5 DAY),
 15.00, 12.00, 48.00, 'Cold Room 3', 'ACTIVE'),

(13, @supplier_id, 'SAL-B001', DATE_SUB(@today, INTERVAL 1 DAY),    DATE_ADD(@today, INTERVAL 2 DAY),
 20.00, 18.00, 65.00, 'Cold Room 3', 'ACTIVE'),

(14, @supplier_id, 'PRA-B001', DATE_SUB(@today, INTERVAL 2 DAY),    DATE_ADD(@today, INTERVAL 1 DAY),
 12.00, 10.00, 36.00, 'Cold Room 3', 'ACTIVE'),

(15, @supplier_id, 'SDB-B001', DATE_SUB(@today, INTERVAL 1 DAY),    DATE_ADD(@today, INTERVAL 3 DAY),
 20.00, 18.00, 10.00, 'Bakery Display', 'ACTIVE'),

(16, @supplier_id, 'CRO-B001', DATE_SUB(@today, INTERVAL 1 DAY),    DATE_ADD(@today, INTERVAL 2 DAY),
 30.00, 28.00, 11.00, 'Bakery Display', 'ACTIVE'),

(17, @supplier_id, 'EGG-B001', @today,                              DATE_ADD(@today, INTERVAL 21 DAY),
 100.00, 100.00, 10.00, 'Cold Room 2', 'ACTIVE'),

(18, @supplier_id, 'COR-B001', DATE_SUB(@today, INTERVAL 1 DAY),    DATE_ADD(@today, INTERVAL 4 DAY),
 25.00, 22.00, 1.50, 'Shelf C1', 'ACTIVE'),

(19, @supplier_id, 'GAR-B001', @today,                              DATE_ADD(@today, INTERVAL 30 DAY),
 50.00, 50.00, 6.50, 'Shelf C2', 'ACTIVE'),

-- 16 batches for new products
(20, @supplier_id, 'POT-B001', DATE_SUB(@today, INTERVAL 4 DAY),    DATE_ADD(@today, INTERVAL 17 DAY),
 50.00, 50.00, 4.00, 'Shelf A1', 'ACTIVE'),
(21, @supplier_id, 'CUC-B001', DATE_SUB(@today, INTERVAL 1 DAY),    DATE_ADD(@today, INTERVAL 9 DAY),
 25.00, 25.00, 5.00, 'Cold Room 1', 'ACTIVE'),
(22, @supplier_id, 'MEL-B001', DATE_SUB(@today, INTERVAL 2 DAY),    DATE_ADD(@today, INTERVAL 8 DAY),
 30.00, 30.00, 8.00, 'Shelf B1', 'ACTIVE'),
(23, @supplier_id, 'CHE-B001', DATE_SUB(@today, INTERVAL 5 DAY),    DATE_ADD(@today, INTERVAL 25 DAY),
 15.00, 15.00, 15.00, 'Cold Room 1', 'ACTIVE'),
(24, @supplier_id, 'MLK-B002', DATE_SUB(@today, INTERVAL 1 DAY),    DATE_ADD(@today, INTERVAL 6 DAY),
 50.00, 50.00, 4.50, 'Cold Room 1', 'ACTIVE'),
(25, @supplier_id, 'CKT-B001', DATE_SUB(@today, INTERVAL 1 DAY),    DATE_ADD(@today, INTERVAL 3 DAY),
 20.00, 20.00, 13.00, 'Cold Room 3', 'ACTIVE'),
(26, @supplier_id, 'BEF-B002', DATE_SUB(@today, INTERVAL 1 DAY),    DATE_ADD(@today, INTERVAL 2 DAY),
 15.00, 15.00, 20.00, 'Cold Room 3', 'ACTIVE'),
(27, @supplier_id, 'MAC-B001', @today,                              DATE_ADD(@today, INTERVAL 2 DAY),
 25.00, 25.00, 12.00, 'Cold Room 3', 'ACTIVE'),
(28, @supplier_id, 'SQD-B001', @today,                              DATE_ADD(@today, INTERVAL 2 DAY),
 18.00, 18.00, 22.00, 'Cold Room 3', 'ACTIVE'),
(29, @supplier_id, 'WHT-B001', DATE_SUB(@today, INTERVAL 1 DAY),    DATE_ADD(@today, INTERVAL 4 DAY),
 20.00, 20.00, 6.00, 'Bakery Display', 'ACTIVE'),
(30, @supplier_id, 'MUF-B001', @today,                              DATE_ADD(@today, INTERVAL 4 DAY),
 24.00, 24.00, 9.00, 'Bakery Display', 'ACTIVE'),
(31, @supplier_id, 'OMG-B001', DATE_SUB(@today, INTERVAL 2 DAY),    DATE_ADD(@today, INTERVAL 19 DAY),
 30.00, 30.00, 12.00, 'Cold Room 2', 'ACTIVE'),
(32, @supplier_id, 'SLK-B001', DATE_SUB(@today, INTERVAL 1 DAY),    DATE_ADD(@today, INTERVAL 13 DAY),
 40.00, 40.00, 2.20, 'Cold Room 1', 'ACTIVE'),
(33, @supplier_id, 'FRM-B001', DATE_SUB(@today, INTERVAL 1 DAY),    DATE_ADD(@today, INTERVAL 13 DAY),
 40.00, 40.00, 2.80, 'Cold Room 1', 'ACTIVE'),
(34, @supplier_id, 'GIN-B001', DATE_SUB(@today, INTERVAL 3 DAY),    DATE_ADD(@today, INTERVAL 18 DAY),
 20.00, 20.00, 4.50, 'Shelf C2', 'ACTIVE'),
(35, @supplier_id, 'LMG-B001', DATE_SUB(@today, INTERVAL 2 DAY),    DATE_ADD(@today, INTERVAL 12 DAY),
 30.00, 30.00, 2.00, 'Shelf C1', 'ACTIVE');

INSERT INTO inventory_logs (stock_batch_id, user_id, movement_type, quantity_change, quantity_after, reason)
SELECT id, @retailer_user_id, 'RESTOCK', original_quantity, quantity_remaining, 'Initial stock from seed'
FROM stock_batches;

-- ============================================================================
-- 9. PRODUCT IMAGES — SVG illustrations (in public/uploads/placeholders/)
-- ============================================================================
INSERT INTO product_images (product_id, image_path, alt_text, is_primary, display_order) VALUES
(1,  'placeholders/lettuce.svg',        'Butterhead Lettuce',         TRUE, 1),
(2,  'placeholders/bokchoy.svg',        'Baby Bok Choy',              TRUE, 1),
(3,  'placeholders/tomato.svg',         'Cherry Tomatoes',            TRUE, 1),
(4,  'placeholders/carrot.svg',         'Cameron Carrots',            TRUE, 1),
(5,  'placeholders/spinach.svg',        'Baby Spinach',               TRUE, 1),
(6,  'placeholders/mango.svg',          'Harum Manis Mango',          TRUE, 1),
(7,  'placeholders/apple.svg',          'Royal Gala Apples',          TRUE, 1),
(8,  'placeholders/banana.svg',         'Pisang Berangan',            TRUE, 1),
(9,  'placeholders/milk.svg',           'Fresh Full Cream Milk',      TRUE, 1),
(10, 'placeholders/yogurt.svg',         'Greek Yogurt',               TRUE, 1),
(11, 'placeholders/chicken.svg',        'Chicken Breast',             TRUE, 1),
(12, 'placeholders/beef.svg',           'Beef Striploin',             TRUE, 1),
(13, 'placeholders/salmon.svg',         'Atlantic Salmon',            TRUE, 1),
(14, 'placeholders/prawns.svg',         'Tiger Prawns',               TRUE, 1),
(15, 'placeholders/sourdough.svg',      'Artisan Sourdough',          TRUE, 1),
(16, 'placeholders/croissant.svg',      'Butter Croissants',          TRUE, 1),
(17, 'placeholders/eggs.svg',           'Grade A Eggs',               TRUE, 1),
(18, 'placeholders/coriander.svg',      'Fresh Coriander',            TRUE, 1),
(19, 'placeholders/garlic.svg',         'Local Garlic',               TRUE, 1),
(20, 'placeholders/potatoes.svg',       'Cameron Potatoes',           TRUE, 1),
(21, 'placeholders/cucumber.svg',       'Japanese Cucumber',          TRUE, 1),
(22, 'placeholders/honeydew.svg',       'Honeydew Melon',             TRUE, 1),
(23, 'placeholders/cheese.svg',         'Cheddar Cheese',             TRUE, 1),
(24, 'placeholders/milk-lowfat.svg',    'Low Fat Milk',               TRUE, 1),
(25, 'placeholders/chicken-thigh.svg',  'Chicken Thigh',              TRUE, 1),
(26, 'placeholders/beef-minced.svg',    'Beef Minced',                TRUE, 1),
(27, 'placeholders/mackerel.svg',       'Fresh Mackerel',             TRUE, 1),
(28, 'placeholders/squid.svg',          'Squid Sotong',               TRUE, 1),
(29, 'placeholders/wheat-bread.svg',    'Whole Wheat Bread',          TRUE, 1),
(30, 'placeholders/muffins.svg',        'Blueberry Muffins',          TRUE, 1),
(31, 'placeholders/omega-eggs.svg',     'Omega-3 Eggs',               TRUE, 1),
(32, 'placeholders/tofu-silken.svg',    'Silken Tofu',                TRUE, 1),
(33, 'placeholders/tofu-firm.svg',      'Firm Tofu',                  TRUE, 1),
(34, 'placeholders/ginger.svg',         'Fresh Ginger',               TRUE, 1),
(35, 'placeholders/lemongrass.svg',     'Lemongrass',                 TRUE, 1);

-- ============================================================================
-- 10. Promo Codes
-- ============================================================================
INSERT INTO promo_codes
    (code, description, discount_type, discount_value, min_order_value, max_discount,
     usage_limit, user_limit, starts_at, expires_at, is_active, created_by)
VALUES
('WELCOME10', '10% off your first order (max RM20)', 'PERCENTAGE', 10.00,
 30.00, 20.00, 1000, 1, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), TRUE, 1),

('FRESH5', 'RM5 off orders above RM50', 'FIXED_AMOUNT', 5.00,
 50.00, NULL, NULL, 3, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), TRUE, 1);

-- ============================================================================
-- 11. System Configuration
-- ============================================================================
INSERT INTO system_config (config_key, config_value, description) VALUES
('site_name',                  'FreshMart',                                  'Display name'),
('site_email',                 'support@freshmart.my',                       'Support email'),
('currency',                   'MYR',                                        'Default currency'),
('currency_symbol',            'RM',                                         'Currency prefix'),
('timezone',                   'Asia/Kuala_Lumpur',                          'System timezone'),
('shipping_fee_default',       '5.00',                                       'Default shipping fee (MYR)'),
('shipping_free_threshold',    '50.00',                                      'Free shipping above this amount'),
('tax_rate',                   '0.00',                                       'Sales tax (0 for now)'),
('guest_cart_hours',           '24',                                         'Hours a guest cart persists'),
('product_image_max_size',     '5242880',                                    'Max image size in bytes (5MB)'),
('product_image_max_count',    '5',                                          'Max images per product'),
('freshness_recalc_minutes',   '5',                                          'How often cron runs'),
('maintenance_mode',           '0',                                          '1 to enable maintenance mode');

-- ============================================================================
-- END OF SEED DATA — 35 products, 35 batches, 8 categories fully populated
-- ============================================================================
