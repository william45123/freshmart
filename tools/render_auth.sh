#!/bin/bash
# Log in as each role and render every console page.
set -u
cd $(cd "$(dirname "$0")/.." && pwd)

rm -f /run/mysqld/mysqld.pid
nohup mariadbd --user=mysql --skip-networking --socket=/run/mysqld/mysqld.sock \
      --log-error=/tmp/mdb.err >/dev/null 2>&1 &
for i in $(seq 1 30); do mariadb -u root -e "SELECT 1" >/dev/null 2>&1 && break; sleep 1; done
mariadb -u root -e "CREATE DATABASE IF NOT EXISTS freshmart CHARACTER SET utf8mb4;" 2>/dev/null
mariadb -u root freshmart < database/freshmart.sql 2>/dev/null

# force a known password for the three test accounts
HASH=$(php -r 'echo password_hash("Test1234!", PASSWORD_DEFAULT);')
mariadb -u root freshmart -e "UPDATE users SET password_hash='$HASH' WHERE email IN ('cherry@example.my','retailer@cameron.my') OR role='ADMIN';"
ADMIN=$(mariadb -u root freshmart -N -e "SELECT email FROM users WHERE role='ADMIN' LIMIT 1")

cp includes/config.php /tmp/config.bak
trap "cp /tmp/config.bak $(cd "$(dirname "$0")/.." && pwd)/includes/config.php 2>/dev/null" EXIT INT TERM
sed -i "s#'http://localhost/freshmart/public'#'http://127.0.0.1:8899'#" includes/config.php
php -S 127.0.0.1:8899 -t public >/tmp/php_server.log 2>&1 &
SRV=$!; sleep 3

pass=0; fail=0
check() {  # $1 label  $2 html  $3 code
  local err=""
  echo "$2" | grep -qiE "Fatal error|Parse error|Warning:|Notice:|Deprecated:|Uncaught" && err="PHP-ERROR"
  [ "$3" != "200" ] && err="HTTP-$3"
  echo "$2" | python3 tools/validate_markup.py - "$1" >/tmp/mk.out 2>&1 || err="MARKUP"
  local leftover
  leftover=$(echo "$2" | grep -o 'style="[^"]*"' | grep -v '^style="--' | head -3)
  [ -n "$leftover" ] && [ -z "$err" ] && err="INLINE-STYLE"
  if [ -n "$err" ]; then
    printf "  %-44s %6s  ✗ %s\n" "$1" "$3" "$err"
    echo "$2" | grep -iE "Fatal error|Parse error|Warning:|Notice:|Deprecated:|Uncaught" | head -2 | sed 's/^/         /'
    [ -n "$leftover" ] && echo "$leftover" | sed 's/^/         /'
    fail=$((fail+1))
  else
    printf "  %-44s %6s  ✓\n" "$1" "$3"; pass=$((pass+1))
  fi
}

login() {  # $1 jar  $2 email
  rm -f "$1"
  local tok
  tok=$(curl -s -c "$1" "http://127.0.0.1:8899/auth/login.php" \
        | grep -o 'name="_csrf" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"//')
  curl -s -b "$1" -c "$1" -o /dev/null -L \
       -d "_csrf=$tok" -d "email=$2" -d "password=Test1234!" \
       "http://127.0.0.1:8899/auth/login.php"
}

run() {  # $1 jar  $2... paths
  local jar=$1; shift
  for p in "$@"; do
    body=$(curl -s -b "$jar" -w "\n@@%{http_code}" "http://127.0.0.1:8899$p")
    check "$p" "$(echo "$body" | sed '$d')" "$(echo "$body" | tail -1 | sed 's/@@//')"
  done
}

echo "═══ CUSTOMER (cherry@example.my) ═══"
login /tmp/j_cust cherry@example.my
OID=$(mariadb -u root freshmart -N -e "SELECT id FROM orders WHERE user_id=3 LIMIT 1")
run /tmp/j_cust /shop/orders.php "/shop/orders.php?id=${OID:-1}" /wishlist.php /wallet.php \
    /notifications.php /profile.php /shop/checkout.php

echo "═══ RETAILER (retailer@cameron.my) ═══"
login /tmp/j_ret retailer@cameron.my
run /tmp/j_ret /retailer/dashboard.php /retailer/products.php /retailer/inventory.php \
    /retailer/orders.php /retailer/refunds.php /retailer/reviews.php \
    /retailer/reports.php /retailer/discounts.php /retailer/profile.php

echo "═══ ADMIN ($ADMIN) ═══"
login /tmp/j_adm "$ADMIN"
run /tmp/j_adm /admin/dashboard.php /admin/users.php /admin/retailers.php /admin/orders.php \
    /admin/refunds.php /admin/reviews.php /admin/promos.php /admin/settings.php

echo "──────────────────────────────────────────────────────────────"
echo "rendered OK: $pass    failed: $fail"
kill $SRV 2>/dev/null
cp /tmp/config.bak includes/config.php
