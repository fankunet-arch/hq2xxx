<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

try {
    require __DIR__ . '/../../../core/config.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo "CONFIG_FAIL: " . $e->getMessage() . "\n";
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "DB_PING_FAIL: \$pdo is not initialized\n";
    exit;
}

try {
    $stmt = $pdo->query('SELECT 1 AS ok');
    $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    echo "DB_PING_OK: " . json_encode($row) . "\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "DB_PING_FAIL: " . $e->getMessage() . "\n";
}
