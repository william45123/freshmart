#!/bin/bash
# Boot MariaDB + the PHP dev server, render every reachable page, and fail on
# any PHP error/warning/notice or unbalanced markup.
set -u
cd $(cd "$(dirname "$0")/.." && pwd)

rm -f /run/mysqld/mysqld.pid
nohup mariadbd --user=mysql --skip-networking --socket=/run/mysqld/mysqld.sock \
      --log-error=/tmp/mdb.err >/dev/null 2>&1 &
for i in $(seq 1 30); do mariadb -u root -e "SELECT 1" >/dev/null 2>&1 && break; sleep 1; done
mariadb -u root -e "CREATE DATABASE IF NOT EXISTS freshmart CHARACTER SET utf8mb4;" 2>/dev/null
mariadb -u root freshmart < database/freshmart.sql 2>/dev/null

# point APP_URL at the dev server for this run only
cp includes/config.php /tmp/config.bak
trap "cp /tmp/config.bak $(cd "$(dirname "$0")/.." && pwd)/includes/config.php 2>/dev/null" EXIT INT TERM
sed -i "s#define('APP_URL',     'http://localhost/freshmart/public');#define('APP_URL',     'http://127.0.0.1:8899');#" includes/config.php

php -S 127.0.0.1:8899 -t public >/tmp/php_server.log 2>&1 &
SRV=$!
sleep 3

SLUG=$(mariadb -u root freshmart -N -e "SELECT slug FROM products LIMIT 1")
PID=$(mariadb -u root freshmart -N -e "SELECT id FROM products LIMIT 1")

PAGES=(
  "/index.php"
  "/shop/browse.php"
  "/shop/browse.php?freshness=LAST_CHANCE"
  "/shop/browse.php?category=vegetables"
  "/shop/browse.php?q=papaya&sort=expiring"
  "/shop/product.php?slug=$SLUG"
  "/shop/cart.php"
  "/shop/freshness.php"
  "/help/freshness.php"
  "/auth/login.php"
  "/auth/register.php"
  "/auth/register.php?as=retailer"
  "/become-retailer.php"
)

pass=0; fail=0
echo "──────────────────────────────────────────────────────────────"
printf "%-46s %6s %8s  %s\n" PAGE HTTP BYTES RESULT
echo "──────────────────────────────────────────────────────────────"
for p in "${PAGES[@]}"; do
  body=$(curl -s -w "\n@@%{http_code}" "http://127.0.0.1:8899$p")
  code=$(echo "$body" | tail -1 | sed 's/@@//')
  html=$(echo "$body" | sed '$d')
  bytes=${#html}
  err=""
  echo "$html" | grep -qiE "Fatal error|Parse error|Warning:|Notice:|Deprecated:|Uncaught" && err="PHP-ERROR"
  [ "$code" != "200" ] && err="HTTP-$code"
  echo "$html" | python3 tools/validate_markup.py - "$p" >/tmp/mk.out 2>&1 || err="MARKUP"
  echo "$html" | grep -q 'style="' && [ -z "$err" ] && {
      leftover=$(echo "$html" | grep -o 'style="[^"]*"' | grep -v '^style="--' | head -3)
      [ -n "$leftover" ] && err="INLINE-STYLE"
  }
  if [ -n "$err" ]; then
    printf "%-46s %6s %8s  ✗ %s\n" "$p" "$code" "$bytes" "$err"
    echo "$html" | grep -iE "Fatal error|Parse error|Warning:|Notice:|Deprecated:|Uncaught" | head -3 | sed 's/^/        /'
    [ "$err" = "INLINE-STYLE" ] && echo "$leftover" | sed 's/^/        /'
    fail=$((fail+1))
  else
    printf "%-46s %6s %8s  ✓\n" "$p" "$code" "$bytes"
    pass=$((pass+1))
  fi
done
echo "──────────────────────────────────────────────────────────────"
echo "rendered OK: $pass    failed: $fail"

echo
echo "=== main.css served ==="
curl -s -o /dev/null -w "  HTTP %{http_code}  %{size_download} bytes  %{content_type}\n" \
     "http://127.0.0.1:8899/assets/css/main.css"

kill $SRV 2>/dev/null
cp /tmp/config.bak includes/config.php
echo "  (config.php restored)"
