<?php
/**
 * Retailer Product create / edit page with image upload.
 *
 *  - GET  ?id=N  → edit existing product
 *  - GET  (no id) → create new
 *  - POST → save (handles both)
 *  - POST delete_image=N → remove an image
 */

require_once __DIR__ . '/../../includes/retailer_layout.php';
require_once __DIR__ . '/../../includes/helpers.php';

$retailer   = retailer_current();
$retailerId = (int) $retailer['id'];

$productId = (int) input('id', 0);
$isEdit    = $productId > 0;
$errors    = [];

// ---------- Load existing product (if editing) ----------
$product = [
    'id' => null, 'name' => '', 'sku' => '', 'description' => '',
    'base_price' => '', 'category_id' => '', 'subcategory_id' => '',
    'unit_type_id' => '', 'shelf_life_days' => '', 'decay_exponent_override' => '',
    'min_order_qty' => '1', 'origin' => '', 'storage_instruction' => '',
    'is_active' => 1, 'is_featured' => 0,
];

if ($isEdit) {
    $row = db_one(
        'SELECT * FROM products WHERE id = ? AND retailer_id = ? AND deleted_at IS NULL',
        [$productId, $retailerId]
    );
    if (!$row) {
        flash_set('error', 'Product not found.');
        redirect('/retailer/products.php');
    }
    $product = $row;
}

// ---------- Handle image deletion ----------
if (is_post() && input('delete_image')) {
    $imgId = (int) input('delete_image');
    if (!csrf_verify()) {
        flash_set('error', 'CSRF mismatch.');
        redirect("/retailer/product_edit.php?id=$productId");
    }
    $img = db_one(
        'SELECT pi.* FROM product_images pi
         JOIN products p ON p.id = pi.product_id
         WHERE pi.id = ? AND p.retailer_id = ?',
        [$imgId, $retailerId]
    );
    if ($img) {
        $filePath = UPLOAD_DIR . '/' . $img['image_path'];
        if (file_exists($filePath)) @unlink($filePath);
        db_run('DELETE FROM product_images WHERE id = ?', [$imgId]);
        flash_set('success', 'Image removed.');
    }
    redirect("/retailer/product_edit.php?id=$productId");
}

// ---------- Handle form submit ----------
if (is_post()) {
    if (!csrf_verify()) {
        $errors[] = 'CSRF token mismatch.';
    } else {
        $product['name']                    = trim((string) input('name', ''));
        $product['sku']                     = trim((string) input('sku', ''));
        $product['description']             = trim((string) input('description', ''));
        $product['base_price']              = (float) input('base_price', 0);
        $product['category_id']             = (int)   input('category_id', 0);
        $product['subcategory_id']          = (int)   input('subcategory_id', 0) ?: null;
        $product['unit_type_id']            = (int)   input('unit_type_id', 0);
        $product['shelf_life_days']         = input('shelf_life_days') !== '' ? (int) input('shelf_life_days') : null;
        $product['decay_exponent_override'] = input('decay_exponent_override') !== '' ? (float) input('decay_exponent_override') : null;
        $product['min_order_qty']           = (float) input('min_order_qty', 1);
        $product['origin']                  = trim((string) input('origin', ''));
        $product['storage_instruction']     = trim((string) input('storage_instruction', ''));
        $product['is_active']               = input('is_active') ? 1 : 0;
        $product['is_featured']             = input('is_featured') ? 1 : 0;

        // Validation
        if ($product['name'] === '')         $errors[] = 'Name is required.';
        if ($product['sku'] === '')          $errors[] = 'SKU is required.';
        if ($product['base_price'] <= 0)     $errors[] = 'Base price must be positive.';
        if ($product['category_id'] <= 0)    $errors[] = 'Category is required.';
        if ($product['unit_type_id'] <= 0)   $errors[] = 'Unit type is required.';

        // SKU uniqueness check
        if (empty($errors)) {
            $existing = db_scalar(
                'SELECT id FROM products WHERE sku = ? AND id != ?',
                [$product['sku'], $productId]
            );
            if ($existing) $errors[] = 'SKU "' . $product['sku'] . '" is already in use.';
        }

        if (empty($errors)) {
            try {
                $slug = slugify($product['name']) . '-' . substr(md5($product['sku']), 0, 6);
                $product['slug'] = $slug;

                $newProductId = db_transaction(function () use ($product, $productId, $retailerId, $isEdit) {
                    if ($isEdit) {
                        db_run(
                            "UPDATE products SET
                                name=?, slug=?, sku=?, description=?, base_price=?,
                                category_id=?, subcategory_id=?, unit_type_id=?,
                                shelf_life_days=?, decay_exponent_override=?, min_order_qty=?,
                                origin=?, storage_instruction=?, is_active=?, is_featured=?
                             WHERE id=? AND retailer_id=?",
                            [
                                $product['name'], $product['slug'], $product['sku'], $product['description'],
                                $product['base_price'], $product['category_id'], $product['subcategory_id'],
                                $product['unit_type_id'], $product['shelf_life_days'],
                                $product['decay_exponent_override'], $product['min_order_qty'],
                                $product['origin'], $product['storage_instruction'],
                                $product['is_active'], $product['is_featured'],
                                $productId, $retailerId,
                            ]
                        );
                        return $productId;
                    }
                    db_run(
                        "INSERT INTO products
                            (retailer_id, name, slug, sku, description, base_price,
                             category_id, subcategory_id, unit_type_id,
                             shelf_life_days, decay_exponent_override, min_order_qty,
                             origin, storage_instruction, is_active, is_featured)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $retailerId, $product['name'], $product['slug'], $product['sku'],
                            $product['description'], $product['base_price'],
                            $product['category_id'], $product['subcategory_id'], $product['unit_type_id'],
                            $product['shelf_life_days'], $product['decay_exponent_override'],
                            $product['min_order_qty'], $product['origin'], $product['storage_instruction'],
                            $product['is_active'], $product['is_featured'],
                        ]
                    );
                    return db_last_id();
                });

                // Handle image uploads
                if (!empty($_FILES['images']['name'][0])) {
                    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);

                    $existingCount = (int) db_scalar(
                        'SELECT COUNT(*) FROM product_images WHERE product_id = ?',
                        [$newProductId]
                    );
                    $hasPrimary = (bool) db_scalar(
                        'SELECT id FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1',
                        [$newProductId]
                    );

                    $maxImages = PRODUCT_IMAGE_MAX;
                    foreach ($_FILES['images']['name'] as $i => $name) {
                        if ($existingCount >= $maxImages) break;
                        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;

                        $tmp  = $_FILES['images']['tmp_name'][$i];
                        $size = $_FILES['images']['size'][$i];
                        if ($size > UPLOAD_MAX_SIZE) {
                            $errors[] = "$name too large (max 5 MB).";
                            continue;
                        }
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime  = finfo_file($finfo, $tmp);
                        finfo_close($finfo);
                        if (!in_array($mime, UPLOAD_ALLOWED_MIME, true)) {
                            $errors[] = "$name has unsupported type ($mime).";
                            continue;
                        }
                        $ext = match($mime) {
                            'image/jpeg' => 'jpg',
                            'image/png'  => 'png',
                            'image/webp' => 'webp',
                            default      => 'jpg',
                        };
                        $newName = sprintf('p%d_%s.%s', $newProductId, random_token(8), $ext);
                        if (!move_uploaded_file($tmp, UPLOAD_DIR . '/' . $newName)) {
                            $errors[] = "Failed to save $name.";
                            continue;
                        }
                        $isPrimary = !$hasPrimary ? 1 : 0;
                        if ($isPrimary) $hasPrimary = true;

                        db_run(
                            "INSERT INTO product_images (product_id, image_path, alt_text, is_primary, display_order)
                             VALUES (?, ?, ?, ?, ?)",
                            [$newProductId, $newName, $product['name'], $isPrimary, $existingCount]
                        );
                        $existingCount++;
                    }
                }

                flash_set('success', $isEdit ? 'Product updated.' : 'Product created.');
                redirect('/retailer/product_edit.php?id=' . $newProductId);

            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

// ---------- Load dropdown data ----------
$categories    = db_all('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY display_order');
$subcategories = db_all('SELECT id, category_id, name FROM subcategories WHERE is_active = 1 ORDER BY display_order');
$unitTypes     = db_all('SELECT id, code, name FROM unit_types ORDER BY display_order');

// Images for this product (if editing)
$images = $isEdit
    ? db_all('SELECT * FROM product_images WHERE product_id = ? ORDER BY display_order, id', [$productId])
    : [];

$pageTitle = $isEdit ? 'Edit Product' : 'New Product';
require_once __DIR__ . '/../../includes/header.php';
retailer_layout_start('products', $isEdit ? 'Edit Product' : 'New Product');
?>

<?php foreach ($errors as $err): ?>
    <div class="flash flash-error"><?= e($err) ?></div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" style="max-width: 720px;">
    <?= csrf_field() ?>

    <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-6); margin-bottom: var(--space-4);">
        <h3 style="margin-top: 0;">Basic Information</h3>

        <div class="form-group">
            <label for="name">Product Name *</label>
            <input type="text" id="name" name="name" required class="form-control"
                   value="<?= attr($product['name']) ?>" maxlength="255">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
            <div class="form-group">
                <label for="sku">SKU *</label>
                <input type="text" id="sku" name="sku" required class="form-control"
                       value="<?= attr($product['sku']) ?>" maxlength="50">
                <div class="form-help">Unique product identifier</div>
            </div>
            <div class="form-group">
                <label for="base_price">Base Price (MYR) *</label>
                <input type="number" id="base_price" name="base_price" step="0.01" min="0.01" required
                       class="form-control" value="<?= attr((string) $product['base_price']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3" class="form-control"
                      maxlength="1000"><?= e($product['description'] ?? '') ?></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--space-4);">
            <div class="form-group">
                <label for="category_id">Category *</label>
                <select id="category_id" name="category_id" required class="form-control">
                    <option value="">— Choose —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $product['category_id'] == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="subcategory_id">Subcategory</label>
                <select id="subcategory_id" name="subcategory_id" class="form-control">
                    <option value="">—</option>
                    <?php foreach ($subcategories as $s): ?>
                        <option value="<?= $s['id'] ?>" data-category="<?= $s['category_id'] ?>"
                                <?= $product['subcategory_id'] == $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="unit_type_id">Unit *</label>
                <select id="unit_type_id" name="unit_type_id" required class="form-control">
                    <option value="">—</option>
                    <?php foreach ($unitTypes as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $product['unit_type_id'] == $u['id'] ? 'selected' : '' ?>>
                            <?= e($u['name']) ?> (<?= e($u['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-6); margin-bottom: var(--space-4);">
        <h3 style="margin-top: 0;">Freshness Settings</h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
            <div class="form-group">
                <label for="shelf_life_days">Shelf Life (days)</label>
                <input type="number" id="shelf_life_days" name="shelf_life_days" min="1" max="365"
                       class="form-control" value="<?= attr((string) ($product['shelf_life_days'] ?? '')) ?>">
                <div class="form-help">Leave blank to use category default</div>
            </div>
            <div class="form-group">
                <label for="decay_exponent_override">Decay Exponent Override</label>
                <input type="number" id="decay_exponent_override" name="decay_exponent_override"
                       step="0.1" min="0.1" max="5.0" class="form-control"
                       value="<?= attr((string) ($product['decay_exponent_override'] ?? '')) ?>">
                <div class="form-help">Leave blank to use category n. Higher = drops faster</div>
            </div>
        </div>
    </div>

    <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-6); margin-bottom: var(--space-4);">
        <h3 style="margin-top: 0;">Details</h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
            <div class="form-group">
                <label for="origin">Origin</label>
                <input type="text" id="origin" name="origin" class="form-control"
                       value="<?= attr($product['origin'] ?? '') ?>" placeholder="e.g. Cameron Highlands">
            </div>
            <div class="form-group">
                <label for="min_order_qty">Min Order Quantity</label>
                <input type="number" id="min_order_qty" name="min_order_qty" step="0.01" min="0.01"
                       class="form-control" value="<?= attr((string) $product['min_order_qty']) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="storage_instruction">Storage Instructions</label>
            <textarea id="storage_instruction" name="storage_instruction" rows="2"
                      class="form-control"><?= e($product['storage_instruction'] ?? '') ?></textarea>
        </div>

        <div style="display: flex; gap: var(--space-4);">
            <label style="display: flex; align-items: center; gap: var(--space-2); cursor: pointer;">
                <input type="checkbox" name="is_active" value="1" <?= $product['is_active'] ? 'checked' : '' ?>>
                Active (visible to customers)
            </label>
            <label style="display: flex; align-items: center; gap: var(--space-2); cursor: pointer;">
                <input type="checkbox" name="is_featured" value="1" <?= $product['is_featured'] ? 'checked' : '' ?>>
                Featured on homepage
            </label>
        </div>
    </div>

    <?php if ($isEdit): ?>
    <div style="background: var(--color-surface); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: var(--space-6); margin-bottom: var(--space-4);">
        <h3 style="margin-top: 0;">Images (max <?= PRODUCT_IMAGE_MAX ?>)</h3>

        <?php if (!empty($images)): ?>
            <div class="image-grid">
                <?php foreach ($images as $img): ?>
                    <div class="image-grid-item <?= $img['is_primary'] ? 'primary' : '' ?>">
                        <img src="<?= upload_url($img['image_path']) ?>" alt="">
                        <form method="post" style="display: inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="delete_image" value="<?= $img['id'] ?>">
                            <button type="submit" class="remove-btn"
                                    onclick="return confirm('Remove this image?')">×</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (count($images) < PRODUCT_IMAGE_MAX): ?>
            <label class="file-upload-area" style="margin-top: var(--space-3); display: block;">
                <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
                       max="<?= PRODUCT_IMAGE_MAX - count($images) ?>">
                <div>📷 Drop images here, or click to select</div>
                <div class="form-help">JPEG / PNG / WEBP, max 5MB each, max <?= PRODUCT_IMAGE_MAX - count($images) ?> more.</div>
            </label>
        <?php endif; ?>
    </div>
    <?php else: ?>
        <div class="flash flash-info">Save the product first, then you can upload images.</div>
    <?php endif; ?>

    <div style="display: flex; gap: var(--space-3);">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Product' ?></button>
        <a href="<?= url('/retailer/products.php') ?>" class="btn btn-ghost">Cancel</a>
    </div>
</form>

<script>
    // Filter subcategories by selected category
    const catSelect = document.getElementById('category_id');
    const subSelect = document.getElementById('subcategory_id');
    const allSubs   = Array.from(subSelect.querySelectorAll('option[data-category]'));
    function filterSubs() {
        const cat = catSelect.value;
        allSubs.forEach(o => o.hidden = (o.dataset.category !== cat));
        if (subSelect.selectedOptions[0]?.hidden) subSelect.value = '';
    }
    catSelect.addEventListener('change', filterSubs);
    filterSubs();
</script>

<?php
retailer_layout_end();
require_once __DIR__ . '/../../includes/footer.php';
