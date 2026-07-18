<?php
/**
 * Database Connection (PDO)
 * ----------------------------------------------------------------
 * Returns a singleton PDO instance for the FreshMart database.
 * Uses prepared statements only — no raw SQL with user input.
 */

require_once __DIR__ . '/config.php';

/**
 * Get the global PDO instance.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00', "
                                     . "NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        if (APP_DEBUG) {
            die('Database connection failed: ' . $e->getMessage());
        }
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(503);
        die('Service temporarily unavailable.');
    }

    return $pdo;
}

/**
 * Convenience: prepare + execute, return statement.
 */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Convenience: fetch all rows.
 */
function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

/**
 * Convenience: fetch single row (or null).
 */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/**
 * Convenience: fetch single scalar column (or null).
 */
function db_scalar(string $sql, array $params = [])
{
    $stmt = db_run($sql, $params);
    $val = $stmt->fetchColumn();
    return $val === false ? null : $val;
}

/**
 * Convenience: get last insert ID.
 */
function db_last_id(): int
{
    return (int) db()->lastInsertId();
}

/**
 * Convenience: run a transaction safely.
 */
function db_transaction(callable $fn)
{
    $pdo = db();

    // Support nested transactions: only the OUTERMOST call actually
    // begins/commits. Inner calls just run within the existing one.
    // (PDO/MySQL don't support real nested transactions, so this
    // prevents "There is already an active transaction" errors when a
    // function that opens a transaction calls another that also does.)
    static $depth = 0;

    if ($depth === 0) {
        $pdo->beginTransaction();
    }
    $depth++;

    try {
        $result = $fn($pdo);
        $depth--;
        if ($depth === 0) {
            $pdo->commit();
        }
        return $result;
    } catch (Throwable $e) {
        $depth--;
        if ($depth === 0 && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
