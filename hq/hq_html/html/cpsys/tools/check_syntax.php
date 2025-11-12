<?php
/**
 * Toptea HQ - 语法健康检查器 (Syntax Health Checker)
 * 版本: 4.0 (终极修复版 - 使用 PHP Linter)
 * 工程师: Gemini
 *
 * 目的:
 * 1. 使用 PHP 内置的 linter (语法检查器) 来查找致命的 Parse Errors (如括号不匹配)。
 * 2. 检查不推荐的 ?> 结尾符。
 *
 * 如何使用:
 * 1. 将此文件放置在 /hq/hq_html/html/cpsys/tools/ 目录下。
 * 2. 在浏览器中访问: https://hq2.toptea.es/cpsys/tools/check_syntax.php
 */

/* --- 输出为纯文本，内置致命错误打印 --- */
@header('Content-Type: text/plain; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '0');
set_exception_handler(function($e){
    http_response_code(500);
    echo "EXCEPTION: {$e->getMessage()}\n{$e->getFile()}:{$e->getLine()}\n".$e->getTraceAsString()."\n";
    exit;
});
register_shutdown_function(function(){
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR], true)) {
        http_response_code(500);
        echo "FATAL: {$e['message']}\n{$e['file']}:{$e['line']}\n";
    }
});

/* --- 路径解析 --- */
$SCRIPT_DIR = realpath(__DIR__);
$CPSYS_DIR  = $SCRIPT_DIR ? realpath(dirname(__DIR__)) : false;
$HTML_ROOT  = $CPSYS_DIR ? realpath(dirname($CPSYS_DIR)) : false;
$HQ_HTML    = $HTML_ROOT ? realpath(dirname($HTML_ROOT)) : false;
$APP_DIR    = $HQ_HTML ? realpath($HQ_HTML . '/app') : false;
$CORE_DIR   = $HQ_HTML ? realpath($HQ_HTML . '/core') : false;
$API_DIR    = $CPSYS_DIR ? realpath($CPSYS_DIR . '/api') : false;
$HELPERS_DIR= $APP_DIR ? realpath($APP_DIR . '/helpers') : false;

$scan_targets = [
    'App Core'  => $CORE_DIR,
    'App Helpers' => $HELPERS_DIR,
    'CPSYS API' => $API_DIR,
];

if (!$APP_DIR || !$CORE_DIR || !$API_DIR || !$HELPERS_DIR) {
    http_response_code(500);
    echo "FATAL: Path resolution failed. One or more core directories not found.\n";
    exit;
}

/* --- 递归文件扫描 --- */
function list_php_recursive($dir): array {
    $out = [];
    $stack = [$dir];
    while ($stack) {
        $d = array_pop($stack);
        $items = @scandir($d);
        if ($items === false) continue;
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;
            $path = $d . DIRECTORY_SEPARATOR . $it;
            if (is_dir($path)) { $stack[] = $path; continue; }
            if (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php') {
                $out[] = realpath($path) ?: $path;
            }
        }
    }
    sort($out);
    return $out;
}

/**
 * V4 - 核心检查函数：使用 PHP Linter
 * @param string $path 文件路径
 * @return array 包含错误信息的数组，如果健康则为空
 */
function analyze_syntax_health_v4(string $path): array {
    $errors = [];
    $raw_content = @file_get_contents($path);
    if ($raw_content === false) {
        $errors[] = "READ_FAIL: 无法读取文件内容。";
        return $errors;
    }

    // --- 检查 1: 检查末尾的 ?> ---
    $trimmed_content = rtrim($raw_content); // 移除末尾所有空白
    if (substr($trimmed_content, -2) === '?>') {
        $errors[] = "WARN: [ 末尾 ?> ] 文件以 `?>` 结尾。不推荐。";
    }

    // --- 检查 2: 检查末尾多余的 } (在 ?> 之后) ---
    $pos_close_tag = strrpos($raw_content, '?>');
    if ($pos_close_tag !== false) {
        $trailing_content = substr($raw_content, $pos_close_tag + 2);
        if (strpos($trailing_content, '}') !== false) {
            $errors[] = "CRITICAL: [ 结尾错误 ] 在 `?>` 之后找到了 `}` 符号。";
        }
    }
    
    // --- 检查 3: 使用 PHP Linter (php_check_syntax) ---
    // (注意: 此函数在 PHP 5.0.4 - 7.4.x 中可用, 在 PHP 8+ 中被移除)
    // (如果服务器是 PHP 8+, 我们将依赖下面的 exec 兜底)
    if (function_exists('php_check_syntax')) {
        // @ 抑制 "deprecated" 警告
        if (!@php_check_syntax($path, $error_message)) {
            if ($error_message) {
                // 清理 linter 输出
                $error_message = preg_replace('/^PHP Parse error:\s*/', '', $error_message);
                $errors[] = "CRITICAL: [ PHP Linter 错误 ] " . $error_message;
            } else {
                $errors[] = "CRITICAL: [ PHP Linter 错误 ] 未知语法错误。";
            }
        }
    } else {
        // --- 检查 3 (兜底): 尝试调用 `php -l` 命令行 ---
        // (这可能因 safe_mode 或 exec 被禁用而失败)
        @exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $return_var);
        
        // $return_var = 0 表示 "No syntax errors detected"
        if ($return_var !== 0) {
            $error_message = implode("\n", $output);
            // 清理 linter 输出
            $error_message = preg_replace('/^Parse error:\s*/', '', $error_message);
            $error_message = str_replace(" in " . $path, "", $error_message);
            $errors[] = "CRITICAL: [ PHP Linter (exec) 错误 ] " . $error_message;
        }
    }

    return $errors;
}

/* --- 执行扫描 --- */
echo "Toptea HQ - 语法健康检查器 (V4.0 - Linter 版)\n";
echo "=================================================\n";
echo "开始扫描...\n\n";

if (!function_exists('php_check_syntax') && !function_exists('exec')) {
     echo "[ 警告 ] `php_check_syntax` 和 `exec` 函数都不可用。检查器将只能检查 `?>` 结尾问题，无法检查括号匹配。\n\n";
}

$total_files_scanned = 0;
$total_files_with_errors = 0;

foreach ($scan_targets as $name => $path) {
    if (!$path) continue;
    
    echo "--- 正在扫描: {$name} ({$path}) ---\n";
    $files = list_php_recursive($path);
    if (empty($files)) {
        echo "  (未找到 .php 文件)\n\n";
        continue;
    }

    $files_in_section = 0;
    $errors_in_section = 0;

    foreach ($files as $file) {
        $files_in_section++;
        // 忽略本工具
        if ($file === $SCRIPT_DIR) continue;
        
        $analysis_errors = analyze_syntax_health_v4($file);
        
        if (!empty($analysis_errors)) {
            $errors_in_section++;
            $relative_path = str_replace($HQ_HTML, '', $file);
            echo "\n[!! ERROR !!] " . $relative_path . "\n";
            foreach ($analysis_errors as $err) {
                echo "  -> " . $err . "\n";
            }
        }
    }
    
    if ($errors_in_section > 0) {
        echo "\n  本节总结: 扫描 {$files_in_section} 个文件, 发现 {$errors_in_section} 个有问题的文件。\n";
    } else {
        echo "  OK: 扫描 {$files_in_section} 个文件, 未发现明显错误。\n";
    }
    
    echo "\n";
    $total_files_scanned += $files_in_section;
    $total_files_with_errors += $errors_in_section;
}

echo "=================================================\n";
echo "扫描完成。\n";
echo "总计扫描文件: {$total_files_scanned}\n";
echo "发现有问题的文件: {$total_files_with_errors}\n";

if ($total_files_with_errors > 0) {
    echo "\n[ 紧急 ] 请修复上面标记为 'CRITICAL' 的文件。\n";
} else {
    echo "\n[ 优秀 ] 未发现语法错误。\n";
}
