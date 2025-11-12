<?php
declare(strict_types=1);

/**
 * Brace Checker (Stable UI + Privacy by Default)
 * Version: 1.3-privacy
 *
 * - 根目录：从本文件回退3层推断为 /hq/hq_html
 * - 表单：输入相对根目录的多行路径（/app、/html/cpsys），保存到 brace_checker.targets.json，并立刻扫描
 * - 仅扫描 PHP 扩展：php, phtml, inc, phpt
 * - 基于 token_get_all 忽略字符串/注释/heredoc/inline HTML，检查 { } 与尾部 "?>"
 * - 默认脱敏显示：不暴露绝对路径；?reveal=1 才显示真实绝对路径（页面与 JSON 同步生效）
 * - JSON 输出：?format=json
 * - 自检：?selftest=1（行数、sha256、函数存在性；同样遵守脱敏逻辑）
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(0);

/* -------- 基本常量 -------- */
$SCRIPT_DIR   = __DIR__;                               // .../hq_html/html/cpsys/tools
$HQHTML_CAND1 = realpath(dirname(__DIR__, 3));         // 回退3层 → .../hq_html
$HQHTML_CAND2 = realpath($SCRIPT_DIR . '/../../..');   // 另一等价写法
$HQHTML       = $HQHTML_CAND1 ?: $HQHTML_CAND2 ?: '';

$CONFIG       = $SCRIPT_DIR . '/brace_checker.targets.json';
$VERSION      = '1.3-privacy';
$EXTS         = ['php','phtml','inc','phpt'];
$IGNORE       = ['/.git/','/vendor/','/node_modules/','/.idea/','/.vscode/','/storage/logs/','/tmp/','/cache/'];

/* -------- 隐私控制（默认脱敏；?reveal=1 临时显示真实值） -------- */
$SHOW_SENSITIVE = (isset($_GET['reveal']) && (string)$_GET['reveal'] !== '' && (string)$_GET['reveal'] !== '0');
$BASE_ALIAS     = '/hq/hq_html';
$CONFIG_ALIAS   = 'brace_checker.targets.json';

/* -------- 小工具 -------- */
function ui_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function starts_with($haystack, $needle){ return substr($haystack, 0, strlen($needle)) === $needle; }

function cfg_load($path){
    if (!is_file($path)) return [];
    $raw = @file_get_contents($path);
    if ($raw === false) return [];
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}
function cfg_save($path, $data){
    $data['updated_at'] = date('c');
    $json = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    return (bool)@file_put_contents($path, $json);
}
function rel_to_abs($rel, $base){
    $rel = trim((string)$rel);
    if ($rel === '') return null;
    if ($rel[0] !== '/') $rel = '/'.$rel;
    $abs = rtrim((string)$base, '/').$rel;
    $real = realpath($abs);
    return ($real !== false && is_dir($real)) ? $real : null;
}
function should_skip($path, $needles){
    $p = str_replace('\\','/',$path);
    foreach ($needles as $n){
        if ($n !== '' && strpos($p, $n) !== false) return true;
    }
    return false;
}
function ends_with_php_close_tag_only($code){
    if (!preg_match('/^\s*<\?php/i', $code)) return null;
    $trim = rtrim($code);
    if ($trim !== '' && substr($trim, -2) === '?>'){
        $pos = strrpos($trim, '?>'); if ($pos === false) return null;
        $pre = substr($trim, 0, $pos);
        return substr_count($pre, "\n") + 1;
    }
    return null;
}
/* 将绝对路径转为相对根的显示用路径（用于脱敏） */
function rel_from_base($abs, $base){
    $p = str_replace('\\','/',$abs);
    $b = rtrim(str_replace('\\','/',$base), '/');
    if ($b !== '' && starts_with($p, $b)){
        $rel = substr($p, strlen($b));
        return ($rel === '') ? '/' : $rel;
    }
    // fallback：只显示文件名，避免暴露层级
    return '/'.basename($p);
}

/* -------- brace 扫描（仅 PHP） -------- */
function php_brace_scan($code){
    $tokens = token_get_all($code);
    $issues = [];
    $stack  = [];
    $line   = 1;
    $in_here = false;

    foreach ($tokens as $tk){
        if (is_array($tk)){
            $type = $tk[0];
            $text = $tk[1];

            // 忽略注释/字符串/inline HTML/heredoc 内容
            if ($type === T_COMMENT || $type === T_DOC_COMMENT ||
                $type === T_CONSTANT_ENCAPSED_STRING || $type === T_ENCAPSED_AND_WHITESPACE ||
                $type === T_INLINE_HTML){
                $line += substr_count($text, "\n");
                continue;
            }
            if ($type === T_START_HEREDOC){ $in_here = true;  $line += substr_count($text, "\n"); continue; }
            if ($type === T_END_HEREDOC){   $in_here = false; $line += substr_count($text, "\n"); continue; }
            if ($in_here){ $line += substr_count($text, "\n"); continue; }

            $line += substr_count($text, "\n");
            continue;
        }
        // 单字符
        $ch = $tk;
        if ($ch === '{'){
            $stack[] = $line;
        } elseif ($ch === '}'){
            if (!$stack){
                $issues[] = ['line'=>$line,'type'=>'extra_close','msg'=>"第 {$line} 行存在多余的 '}'（无对应的 '{'）"];
            } else {
                array_pop($stack);
            }
        }
    }

    foreach ($stack as $openLine){
        $issues[] = ['line'=>$openLine,'type'=>'missing_close','msg'=>"第 {$openLine} 行的 '{' 缺少匹配的 '}'"];
    }
    return $issues;
}

/* -------- 递归扫描 -------- */
function run_scan($absDirs, $exts, $ignore){
    $out = [];
    foreach ($absDirs as $base){
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $fi){
            $path = $fi->getPathname();
            if ($fi->isDir()){
                if (should_skip($path.'/', $ignore)) continue;
                continue;
            }
            if (should_skip($path, $ignore)) continue;

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, $exts, true)) continue;

            $code = @file_get_contents($path);
            if ($code === false){
                $out[] = ['file'=>$path,'issues'=>[['line'=>0,'type'=>'io_error','msg'=>'无法读取文件内容']],'close_tag_line'=>null];
                continue;
            }
            $issues = php_brace_scan($code);
            $close  = ends_with_php_close_tag_only($code);
            $out[]  = ['file'=>$path,'issues'=>$issues,'close_tag_line'=>$close];
        }
    }
    return $out;
}

/* -------- 参数处理 -------- */
$format = isset($_GET['format']) ? strtolower((string)$_GET['format']) : 'html';
$want_json = ($format === 'json');
$reset     = (isset($_GET['reset']) && $_GET['reset'] == '1');
$selftest  = (isset($_GET['selftest']) && $_GET['selftest'] == '1');

/* -------- 自检 -------- */
if ($selftest){
    @header('Content-Type: text/plain; charset=utf-8');
    $lines = @file(__FILE__); $lines = is_array($lines) ? count($lines) : 0;
    $sha   = @hash_file('sha256', __FILE__) ?: 'n/a';
    $path_disp = $SHOW_SENSITIVE ? __FILE__ : basename(__FILE__);
    $base_disp = $SHOW_SENSITIVE ? ($HQHTML ?: '(not found)') : $BASE_ALIAS;
    echo "SELFTEST v{$VERSION}\n";
    echo "file    : {$path_disp}\n";
    echo "lines   : {$lines}\n";
    echo "sha256  : {$sha}\n";
    echo "base    : {$base_disp}\n";
    echo "func    : php_brace_scan=". (function_exists('php_brace_scan')?'OK':'MISSING') .
         ", run_scan=".(function_exists('run_scan')?'OK':'MISSING') .
         ", token_get_all=".(function_exists('token_get_all')?'OK':'MISSING') . "\n";
    echo "privacy : ".($SHOW_SENSITIVE ? 'reveal=ON' : 'masked')."\n";
    exit;
}

/* -------- 重置配置 -------- */
if ($reset){ @unlink($CONFIG); }

/* -------- 读取/保存目录配置（相对 /hq/hq_html） -------- */
$saved = cfg_load($CONFIG);
$dirs_rel = [];
if (isset($saved['dirs_rel']) && is_array($saved['dirs_rel'])) $dirs_rel = $saved['dirs_rel'];

if (isset($_POST['dirs_rel'])){
    $rows = preg_split('/\R/u', (string)$_POST['dirs_rel']);
    $new_rel = [];
    foreach ($rows as $r){
        $r = trim($r);
        if ($r === '' || starts_with($r, '#') || starts_with($r, '//')) continue;
        if ($r[0] !== '/') $r = '/'.$r;
        $new_rel[] = $r;
    }
    if ($new_rel){
        $dirs_rel = $new_rel;
        cfg_save($CONFIG, ['dirs_rel'=>$dirs_rel]);
    }
}

/* -------- 解析为绝对路径并扫描 -------- */
$absDirs = [];
$invalid_rel = [];
foreach ($dirs_rel as $rel){
    $abs = rel_to_abs($rel, $HQHTML);
    if ($abs) $absDirs[] = $abs; else $invalid_rel[] = $rel;
}

$results = [];
$summary = [
    'version' => $VERSION,
    'privacy' => $SHOW_SENSITIVE ? 'reveal' : 'masked',
    'base_root' => $SHOW_SENSITIVE ? ($HQHTML ?: '(not found)') : $BASE_ALIAS,
    'config'    => $SHOW_SENSITIVE ? $CONFIG : $CONFIG_ALIAS,
    'dirs_rel'  => $dirs_rel,
    'dirs_abs'  => [],          // 根据隐私设置填充
    'invalid_rel' => $invalid_rel,
    'total_files' => 0,
    'files_with_brace_issues' => 0,
    'files_with_trailing_php_close_tag' => 0,
    'brace_issue_count' => 0,
    'elapsed_sec' => 0.0,
];

if ($absDirs){
    $start = microtime(true);
    $results = run_scan($absDirs, $EXTS, $IGNORE);
    $elapsed = microtime(true) - $start;

    // 汇总
    $total = count($results);
    $filesIssues = 0; $filesClose = 0; $issueCount = 0;
    foreach ($results as $r){
        if (!empty($r['issues'])){ $filesIssues++; $issueCount += count($r['issues']); }
        if ($r['close_tag_line'] !== null) $filesClose++;
    }

    // 结果中的文件路径：默认显示相对根路径（脱敏）；reveal=1 时显示绝对路径
    $displayResults = [];
    foreach ($results as $r){
        $dispFile = $SHOW_SENSITIVE ? $r['file'] : rel_from_base($r['file'], $HQHTML);
        $displayResults[] = [
            'file' => $dispFile,
            'issues' => $r['issues'],
            'close_tag_line' => $r['close_tag_line'],
        ];
    }
    $results = $displayResults;

    // 目录绝对路径显示
    if ($SHOW_SENSITIVE){
        $summary['dirs_abs'] = $absDirs;
    } else {
        $summary['dirs_abs'] = array_map(function($p) use ($HQHTML){ return rel_from_base($p, $HQHTML); }, $absDirs);
    }

    $summary['total_files'] = $total;
    $summary['files_with_brace_issues'] = $filesIssues;
    $summary['files_with_trailing_php_close_tag'] = $filesClose;
    $summary['brace_issue_count'] = $issueCount;
    $summary['elapsed_sec'] = round($elapsed, 3);
}

/* -------- JSON 输出 -------- */
if ($want_json){
    @header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true,'summary'=>$summary,'results'=>$results], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    exit;
}

/* -------- HTML 输出（总是显示状态 + 表单；若已扫描则展示结果） -------- */
@header('Content-Type: text/html; charset=utf-8');
$displayRoot = $SHOW_SENSITIVE ? ($HQHTML ?: '(未找到)') : $BASE_ALIAS;
$displayCfg  = $SHOW_SENSITIVE ? $CONFIG : $CONFIG_ALIAS;

echo '<!doctype html><meta charset="utf-8"><title>Brace Checker '.$VERSION.'</title>';
echo '<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#0b0f19;color:#e5e7eb;margin:24px}
h3{margin:0 0 8px 0}
small{color:#93c5fd}
pre{background:#111;padding:10px;color:#ddd;max-width:1200px;overflow:auto}
textarea{width:100%;max-width:900px;height:140px;background:#0b1220;border:1px solid #263244;border-radius:10px;color:#e5e7eb;padding:10px;font-family:ui-monospace,Consolas,Monaco,Menlo,monospace}
button,.btn{background:#2563eb;border:0;color:#fff;padding:8px 12px;border-radius:10px;cursor:pointer;font-weight:600;text-decoration:none;display:inline-block;margin-right:8px}
.btn.link{background:#374151}
.warn{color:#fca5a5}
.meta{color:#93c5fd}
</style>';

echo '<h3>Brace Checker <small class="meta">'.ui_h($VERSION).'</small></h3>';
echo '<div>基准根：<code>'.ui_h($displayRoot).'</code></div>';
echo '<div>配置文件：<code>'.ui_h($displayCfg).'</code></div>';
echo '<div style="margin:8px 0 12px 0">';
echo '已保存目录数：'.count($dirs_rel).'，解析成功：'.count($summary['dirs_abs']).'，无效：'.count($summary['invalid_rel']).'<br>';
if ($summary['total_files'] > 0){
    echo '扫描文件：'.$summary['total_files'].'；花括号问题文件：'.$summary['files_with_brace_issues'].'；尾部含 ?> 文件：'.$summary['files_with_trailing_php_close_tag'].'；总问题：'.$summary['brace_issue_count'].'；耗时：'.$summary['elapsed_sec'].'s';
} else {
    echo '尚未扫描：请在下方输入目录并点击“开始扫描并保存”。';
}
echo '</div>';

if (!empty($summary['invalid_rel'])){
    echo '<div class="warn">无效目录（相对 /hq/hq_html）：<code>'.ui_h(implode(', ', $summary['invalid_rel'])).'</code></div>';
}

echo '<h4 style="margin-top:12px">扫描目录（每行一个，相对 /hq/hq_html）</h4>';
$prefill = ui_h(implode("\n", $dirs_rel));
$revealLink = $SHOW_SENSITIVE ? '?' : '?reveal=1';
echo '<form method="post"><textarea name="dirs_rel">'.$prefill.'</textarea><br>';
echo '<button type="submit">开始扫描并保存</button> ';
echo '<a class="btn link" href="?reset=1">清空记忆</a> ';
echo '<a class="btn link" href="?selftest=1'.($SHOW_SENSITIVE?'&reveal=1':'').'">自检</a> ';
echo '<a class="btn link" href="?format=json'.($SHOW_SENSITIVE?'&reveal=1':'').'">JSON</a> ';
echo '<a class="btn link" href="'.$revealLink.'">'.($SHOW_SENSITIVE?'隐藏敏感':'显示敏感').'</a>';
echo '</form>';

if ($summary['total_files'] > 0){
    echo '<h4 style="margin-top:16px">扫描结果</h4>';
    echo '<pre>';
    foreach ($results as $r){
        $flag = (!empty($r['issues']) || $r['close_tag_line'] !== null) ? '[!]' : '[OK]';
        echo $flag.' '.ui_h($r['file'])."\n";
        if ($r['close_tag_line'] !== null){
            echo "  - tail '?>' at line ".(int)$r['close_tag_line']."\n";
        }
        if (!empty($r['issues'])){
            foreach ($r['issues'] as $iss){
                echo '  - '.ui_h($iss['msg'])."\n";
            }
        }
    }
    echo '</pre>';
}
