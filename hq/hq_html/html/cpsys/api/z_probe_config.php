<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

try {
    require __DIR__ . '/../../../core/config.php';
    echo "CONFIG_OK\n";
    echo 'APP_PATH=' . (defined('APP_PATH') ? APP_PATH : 'UNDEF') . "\n";
    echo 'ROOT_PATH=' . (defined('ROOT_PATH') ? ROOT_PATH : 'UNDEF') . "\n";
    echo '$pdo=' . ((isset($pdo) && $pdo instanceof PDO) ? 'READY' : 'NULL') . "\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "CONFIG_FAIL: " . $e->getMessage() . "\n";
}
