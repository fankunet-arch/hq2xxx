<?php
/**
 * Helpers Tag Audit v3 (single-file, safe, no-SPL)
 * Path: /hq/hq_html/html/cpsys/tools/helpers_tag_audit.php
 *
 * - action=ping            : 路径/权限自检（只读）
 * - action=scan            : 扫描 helpers 是否有 BOM / 末尾 "?>" / 末尾空白
 * - action=fix&confirm=YES : 备份后修复（去 BOM、去末尾 "?>" 及其后的空白，结尾标准化为 "\n"）
 * - &from_includes=1       : 仅扫描 /hq/hq_html/html 中实际 include/require 的 helpers
 *
 * 只此一个文件；执行完可删除。
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

/* --- 小工具 --- */
if (!function_exists('str_starts_with')) {
    function str_starts_with($h, $n){ return $n !== '' && substr($h, 0, strlen($n)) === $n; }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($h, $n){ return $n !== '' && substr($h, -strlen($n)) === $n; }
}
function ok($msg){ echo $msg . "\n"; }
function fail($msg){ http_response_code(500); echo $msg . "\n"; exit; }

/* --- 路径解析（以本文件为基准）---
 * 本文件： /hq/hq_html/html/cpsys/tools/helpers_tag_audit.php
 * dirname(__DIR__,1) = /hq/hq_html/html/cpsys
 * dirname(__DIR__,2) = /hq/hq_html/html
 * dirname(__DIR__,3) = /hq/hq_html
 */
$SCRIPT_DIR = realpath(__DIR__);
$CPSYS_DIR  = $SCRIPT_DIR ? realpath(dirname(__DIR__)) : false;        // /html/cpsys
$HTML_ROOT  = $CPSYS_DIR ? realpath(dirname(__DIR__, 2)) : false;      // /html
$HQ_HTML    = $HTML_ROOT ? realpath(dirname($HTML_ROOT)) : false;      // /hq/hq_html
$APP_DIR    = $HQ_HTML ? realpath($HQ_HTML . '/app') : false;
$HELPERS_DIR= $APP_DIR ? realpath($APP_DIR . '/helpers') : false;

$action = isset($_GET['action']) ? strtolower(trim($_GET['action'])) : 'scan';
$from_includes = !empty($_GET['from_includes']);
$confirm = isset($_GET['confirm']) ? strtoupper(trim($_GET['confirm'])) : '';

/* --- 保障路径存在 --- */
if (!$SCRIPT_DIR || !$CPSYS_DIR || !$HTML_ROOT || !$HQ_HTML || !$APP_DIR || !$HELPERS_DIR) {
    fail("Path resolution failed.\n"
        ."SCRIPT_DIR={$SCRIPT_DIR}\nCPSYS_DIR={$CPSYS_DIR}\nHTML_ROOT={$HTML_ROOT}\nHQ_HTML={$HQ_HTML}\nAPP_DIR={$APP_DIR}\nHELPERS_DIR={$HELPERS_DIR}\n");
}

/* --- 简单递归（不用 SPL，避免环境差异） --- */
function list_php_recursive($dir){
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

/* --- 从 html 根目录解析 “被包含的 helpers” --- */
function list_included_helpers($html_root, $helpers_root){
    $helpers_root_real = realpath($helpers_root);
    $out = [];
    $stack = [$html_root];
    while ($stack) {
        $d = array_pop($stack);
        $items = @scandir($d);
        if ($items === false) continue;
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') continue;
            $path = $d . DIRECTORY_SEPARATOR . $it;
            if (is_dir($path)) { $stack[] = $path; continue; }
            if (!is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') continue;

            $code = @file_get_contents($path);
            if ($code === false) continue;

            // 捕捉 include/require 里的字符串常量
            if (preg_match_all('/(?:require|include)(_once)?\s*\((.*?)\)\s*;|(?:require|include)(_once)?\s+([^;]+);/i', $code, $m, PREG_SET_ORDER)) {
                foreach ($m as $mm) {
                    $expr = $mm[2] ?? ($mm[4] ?? '');
                    if (!$expr) continue;
                    if (preg_match_all('/[\'"]([^\'"]*helpers[^\'"]*\.php)[\'"]/', $expr, $pm)) {
                        foreach ($pm[1] as $rel) {
                            if ($rel !== '' && $rel[0] === '/' && file_exists($rel)) {
                                $real = realpath($rel);
                                if ($real && str_starts_with($real, $helpers_root_real)) $out[$real] = true;
                                continue;
                            }
                            $try1 = realpath(dirname($path) . '/' . $rel);
                            if ($try1 && str_starts_with($try1, $helpers_root_real)) { $out[$try1] = true; continue; }
                            $try2 = realpath($helpers_root_real . '/' . ltrim($rel,'/'));
                            if ($try2 && str_starts_with($try2, $helpers_root_real)) { $out[$try2] = true; continue; }
                        }
                    }
                }
            }
        }
    }
    $keys = array_keys($out);
    sort($keys);
    return $keys;
}

/* --- 分析与修复 --- */
function analyze_php($path){
    $raw = @file_get_contents($path);
    if ($raw === false) {
        return ['file'=>$path,'read_fail'=>true];
    }
    $has_bom = (strncmp($raw, "\xEF\xBB\xBF", 3) === 0);
    $trimmed = rtrim($raw, "\r\n\t \0\x0B");
    $ends_close = str_ends_with($trimmed, '?>');

    $trailing_ws = false;
    if ($ends_close) {
        $pos = strrpos($raw, '?>');
        if ($pos !== false && $pos < strlen($raw) - 2) {
            $after = substr($raw, $pos + 2);
            $trailing_ws = ($after !== '' && trim($after) === '');
        }
    }
    return [
        'file' => $path,
        'size' => strlen($raw),
        'has_bom' => $has_bom,
        'ends_with_close_tag' => $ends_close,
        'trailing_ws_after_close' => $trailing_ws,
        'needs_fix' => ($has_bom || $ends_close || $trailing_ws)
    ];
}

function fix_php($path, $analysis, &$backup_or_err){
    $raw = @file_get_contents($path);
    if ($raw === false) { $backup_or_err = 'READ_FAIL'; return false; }

    $changed = false;
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) { $raw = substr($raw, 3); $changed = true; }

    $trimmed = rtrim($raw, "\r\n\t \0\x0B");
    if (str_ends_with($trimmed, '?>')) {
        $pos = strrpos($raw, '?>');
        if ($pos !== false) { $raw = substr($raw, 0, $pos); $changed = true; }
    }
    $raw = rtrim($raw, "\r\n\t \0\x0B") . "\n";

    if ($changed) {
        $bak = $path . '.bak.' . date('YmdHis');
        if (!@copy($path, $bak)) { $backup_or_err = 'BACKUP_FAIL'; return false; }
        if (@file_put_contents($path, $raw) === false) { $backup_or_err = 'WRITE_FAIL'; return false; }
        $backup_or_err = $bak;
        return true;
    }
    $backup_or_err = null;
    return true;
}

/* --- 动作：ping --- */
if ($action === 'ping') {
    ok("status: PONG");
    ok("php: ".PHP_VERSION);
    ok("SCRIPT_DIR:  {$SCRIPT_DIR}");
    ok("CPSYS_DIR:   {$CPSYS_DIR}");
    ok("HTML_ROOT:   {$HTML_ROOT}");
    ok("HQ_HTML:     {$HQ_HTML}");
    ok("APP_DIR:     {$APP_DIR}");
    ok("HELPERS_DIR: {$HELPERS_DIR}");
    ok("helpers_readable: ".(is_readable($HELPERS_DIR)?'1':'0'));
    ok("helpers_writable: ".(is_writable($HELPERS_DIR)?'1':'0'));
    exit;
}

/* --- 构建目标文件列表 --- */
$all_helpers = list_php_recursive($HELPERS_DIR);
$targets = $all_helpers;
if ($from_includes) {
    $inc = list_included_helpers($HTML_ROOT, $HELPERS_DIR);
    if (!empty($inc)) $targets = $inc;
}

ok("env.php=".PHP_VERSION);
ok("roots:");
ok("  HTML_ROOT=".$HTML_ROOT);
ok("  HELPERS_DIR=".$HELPERS_DIR);
ok("totals: all_helpers=".count($all_helpers).", targets=".count($targets));
ok(str_repeat('-', 48));

/* --- 执行 --- */
$need_fix = 0; $done_fix = 0; $errors = 0;
foreach ($targets as $f) {
    $a = analyze_php($f);
    if (!empty($a['read_fail'])) {
        ok("READ_FAIL: ".$f);
        $errors++; continue;
    }
    $line = ($a['needs_fix'] ? '[NEED_FIX]' : '[OK]').' '.$f
          .' | bom='.($a['has_bom']?'1':'0')
          .' | close?>='.($a['ends_with_close_tag']?'1':'0')
          .' | tail_ws='.($a['trailing_ws_after_close']?'1':'0')
          .' | size='.$a['size'];
    ok($line);

    if ($a['needs_fix']) $need_fix++;

    if ($action === 'fix' && $confirm === 'YES' && $a['needs_fix']) {
        $bak_or_err = null;
        $res = fix_php($f, $a, $bak_or_err);
        if ($res === true) {
            $done_fix++;
            ok("  -> FIXED ".($bak_or_err ? " (backup: $bak_or_err)" : ""));
        } else {
            $errors++;
            ok("  -> FIX_FAIL ($bak_or_err)");
        }
    }
}

ok(str_repeat('-', 48));
ok("summary: need_fix={$need_fix}, fixed={$done_fix}, errors={$errors}");
