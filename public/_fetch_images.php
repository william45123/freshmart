<?php
/**
 * FreshMart — product photo downloader (PHP version, NO Python needed)
 * ============================================================================
 * Downloads ONE real, free-to-use photo per product from Pexels into
 *     public/uploads/products/<name>.jpg
 * ...then automatically updates the database to point at the new photos.
 *
 * Pexels License = free, commercial use OK, NO attribution required — safe for
 * a portfolio / FYP demo (unlike Google Images, which are copyrighted).
 *
 * ----------------------------------------------------------------------------
 * HOW TO RUN (easiest way — your browser):
 *
 *   1. Get a free Pexels API key:  https://www.pexels.com/api/  ->  "Get Started"
 *        (sign up, copy the API key it shows you)
 *
 *   2. Paste that key into the $PEXELS_API_KEY line just below.
 *
 *   3. Put THIS file inside your project's  public/  folder
 *        (so it sits next to index.php), named e.g.  _fetch_images.php
 *
 *   4. Make sure XAMPP (Apache + MySQL) is running, then open in your browser:
 *        http://localhost/freshmart/public/_fetch_images.php
 *        (use whatever URL you normally open FreshMart at, + /_fetch_images.php)
 *
 *   5. Watch the lines scroll. When it says "DONE", hard-refresh your site
 *        (Ctrl+Shift+R). Photos are live.
 *
 *   6. IMPORTANT: delete this file afterwards — it's a one-time tool and is
 *      not password-protected.
 *
 * (You can also run it from a terminal:  php public/_fetch_images.php  — but the
 *  browser way avoids Windows PATH issues.)
 * ----------------------------------------------------------------------------
 */

// ====== 1) PASTE YOUR PEXELS API KEY BETWEEN THE QUOTES ======
$PEXELS_API_KEY = 'pZ10ih3BPD53BWYfsQuEwErnw14sNMygXnupphzpWyUWmo50fwPwrKeM';

// If you get an SSL / "certificate" error on XAMPP, change this to false:
$VERIFY_SSL = true;

// ---------------------------------------------------------------------------
// (no need to edit below here)
// ---------------------------------------------------------------------------
@set_time_limit(0);                 // 35 downloads can take a while
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}
while (ob_get_level() > 0) { ob_end_flush(); }   // show progress line-by-line

function say(string $line): void { echo $line . "\n"; @flush(); }

$root    = dirname(__DIR__);                       // project root (this file is in /public)
$outDir  = $root . '/public/uploads/products';
$configF = $root . '/includes/config.php';

// product image base-name  =>  Pexels search query
// (keys match your existing placeholders/<name>.svg so the DB update lines up)
$products = [
    'lettuce'       => 'butterhead lettuce',
    'bokchoy'       => 'bok choy vegetable',
    'tomato'        => 'cherry tomatoes',
    'carrot'        => 'fresh carrots',
    'spinach'       => 'baby spinach leaves',
    'mango'         => 'ripe mango fruit',
    'apple'         => 'gala apples',
    'banana'        => 'bananas bunch',
    'milk'          => 'milk bottle dairy',
    'yogurt'        => 'greek yogurt bowl',
    'chicken'       => 'raw chicken breast',
    'beef'          => 'raw beef steak',
    'salmon'        => 'raw salmon fillet',
    'prawns'        => 'raw prawns shrimp',
    'sourdough'     => 'sourdough bread loaf',
    'croissant'     => 'butter croissants',
    'eggs'          => 'fresh eggs carton',
    'coriander'     => 'fresh coriander cilantro',
    'garlic'        => 'garlic bulbs',
    'potatoes'      => 'potatoes',
    'cucumber'      => 'cucumber vegetable',
    'honeydew'      => 'honeydew melon',
    'cheese'        => 'cheddar cheese block',
    'milk-lowfat'   => 'milk carton',
    'chicken-thigh' => 'raw chicken thigh',
    'beef-minced'   => 'minced beef ground meat',
    'mackerel'      => 'mackerel fish fresh',
    'squid'         => 'raw squid seafood',
    'wheat-bread'   => 'whole wheat bread loaf',
    'muffins'       => 'blueberry muffins',
    'omega-eggs'    => 'brown eggs',
    'tofu-silken'   => 'silken tofu',
    'tofu-firm'     => 'firm tofu block',
    'ginger'        => 'ginger root',
    'lemongrass'    => 'lemongrass stalks',
];

// --- guards ---------------------------------------------------------------
if (trim($PEXELS_API_KEY) === '') {
    say("STOP: open this file and paste your Pexels API key into \$PEXELS_API_KEY.");
    say("Get one free at https://www.pexels.com/api/");
    exit;
}
if (!is_dir($outDir) && !@mkdir($outDir, 0775, true)) {
    say("STOP: could not create folder $outDir (check permissions).");
    exit;
}

// --- tiny HTTP helper (cURL, with file_get_contents fallback) -------------
function http_get(string $url, array $headers, bool $verify): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 40,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            CURLOPT_USERAGENT      => 'FreshMart-image-fetch',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return [$body === false ? '' : $body, $code, $err];
    }
    $ctx  = stream_context_create(['http' => ['header' => implode("\r\n", $headers), 'timeout' => 40]]);
    $body = @file_get_contents($url, false, $ctx);
    return [$body === false ? '' : $body, $body === false ? 0 : 200,
            $body === false ? 'no curl + file_get_contents failed' : ''];
}

// --- download loop --------------------------------------------------------
$total = count($products);
$i = 0; $ok = 0; $skip = 0; $fail = [];
$done = [];   // base-names we actually saved -> used for the DB update

say("FreshMart product photos — downloading $total items into public/uploads/products/");
say(str_repeat('-', 60));

foreach ($products as $name => $query) {
    $i++;
    $dest = "$outDir/$name.jpg";
    if (is_file($dest)) {
        say(sprintf("[%2d/%d] skip  %-14s (already exists)", $i, $total, $name));
        $skip++; $done[] = $name;   // still relink it in the DB
        continue;
    }

    $searchUrl = 'https://api.pexels.com/v1/search?' . http_build_query([
        'query' => $query, 'per_page' => 1, 'orientation' => 'square',
    ]);
    [$body, $code, $err] = http_get($searchUrl, ["Authorization: $PEXELS_API_KEY"], $VERIFY_SSL);

    if ($code === 401) { say("STOP: Pexels says your API key is invalid (401). Check the key."); break; }
    if ($code !== 200) {
        say(sprintf("[%2d/%d] FAIL  %-14s (search HTTP %d %s)", $i, $total, $name, $code, $err));
        $fail[] = $name; continue;
    }

    $photos = json_decode($body, true)['photos'] ?? [];
    if (!$photos) {
        say(sprintf("[%2d/%d] MISS  %-14s (no result for '%s')", $i, $total, $name, $query));
        $fail[] = $name; continue;
    }

    $imgUrl = $photos[0]['src']['large'];   // ~940px, square — plenty for cards
    [$imgBody, $imgCode] = http_get($imgUrl, [], $VERIFY_SSL);
    if ($imgCode !== 200 || $imgBody === '') {
        say(sprintf("[%2d/%d] FAIL  %-14s (download HTTP %d)", $i, $total, $name, $imgCode));
        $fail[] = $name; continue;
    }

    file_put_contents($dest, $imgBody);
    say(sprintf("[%2d/%d] OK    %-14s (%d KB)", $i, $total, $name, (int) (strlen($imgBody) / 1024)));
    $ok++; $done[] = $name;
    usleep(300000);   // 0.3s — be gentle on the API
}

say(str_repeat('-', 60));
say("Downloaded $ok new, skipped $skip, failed " . count($fail) . ".");
if ($fail) say("Couldn't get: " . implode(', ', $fail) . "  (re-run, or grab manually from pexels.com)");

// --- relink the database to the new photos (only the ones we have) --------
if ($done) {
    try {
        require_once $configF;   // defines DB_HOST, DB_NAME, DB_USER, DB_PASS, ...
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
                       DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $stmt = $pdo->prepare(
            "UPDATE product_images SET image_path = ? WHERE image_path = ?"
        );
        $rows = 0;
        foreach ($done as $name) {
            $stmt->execute(["products/$name.jpg", "placeholders/$name.svg"]);
            $rows += $stmt->rowCount();
        }
        say("Database updated: $rows image rows now point to products/*.jpg");
    } catch (Throwable $e) {
        say("DB auto-update skipped (" . $e->getMessage() . ").");
        say("Run this once in phpMyAdmin instead:");
        say("  UPDATE product_images SET image_path = CONCAT('products/',");
        say("    SUBSTRING_INDEX(SUBSTRING_INDEX(image_path,'/',-1),'.',1), '.jpg')");
        say("  WHERE image_path LIKE 'placeholders/%';");
    }
}

say("");
say("DONE. Hard-refresh your site (Ctrl+Shift+R), then DELETE this file.");
