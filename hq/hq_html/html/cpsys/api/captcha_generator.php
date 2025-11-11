<?php
/**
 * Toptea HQ - cpsys
 * Simple CAPTCHA Image Generator (robust)
 * Engineer: (保持原署名) | Patched: 2025-11-10
 *
 * 关键修复：
 * - 确保无任何输出在 PNG 之前（清空缓冲、关闭错误输出、防 BOM）
 * - 先发 Content-Type/Cache 头
 * - GD 不存在时输出 1x1 PNG 兜底，避免 500
 */

declare(strict_types=1);

// —— 防止任何文字/警告污染二进制输出 ——
@ini_set('display_errors', '0');
@error_reporting(0);
while (ob_get_level() > 0) { @ob_end_clean(); }  // 清理可能的 BOM/空白/缓冲

// —— 会话 ——（不输出任何内容）
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

// —— 先发响应头 ——（务必在任何输出前）
header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// —— 若 GD 不可用：输出 1x1 透明 PNG 兜底，避免致命错误 —— 
if (!function_exists('imagecreatetruecolor')) {
    // iVBORw0... 是 1x1 透明 PNG 的 Base64
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGMAAQAABQABDQottAAAAABJRU5ErkJggg==');
    exit;
}

// --- Configuration ---
$width = 120;
$height = 40;
$length = 4; // Number of characters
$characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // Avoid 1,I,0,O 等混淆字符

// --- Generate Code ---
$code = '';
$chars_len = strlen($characters);
for ($i = 0; $i < $length; $i++) {
    // 用 random_int 更稳（CSPRNG），兼容性好
    $code .= $characters[random_int(0, $chars_len - 1)];
}
// 存 session（不区分大小写比较）
$_SESSION['captcha_code'] = strtolower($code);

// --- Create Image ---
$image = imagecreatetruecolor($width, $height);
$bg_color   = imagecolorallocate($image, 33, 37, 41);   // #212529 深背景
$text_color = imagecolorallocate($image, 237, 119, 98); // #ED7762 品牌色
$line_color = imagecolorallocate($image, 60, 65, 70);   // 噪线

imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);

// 随机噪声线
for ($i = 0; $i < 5; $i++) {
    imageline($image, 0, random_int(0, $height), $width, random_int(0, $height), $line_color);
}

// 文本（内置字体回退，无 TTF 依赖）
$font = 5;
$x = (int)(($width - imagefontwidth($font) * strlen($code)) / 2);
$y = (int)(($height - imagefontheight($font)) / 2);
imagestring($image, $font, $x, $y, $code, $text_color);

// --- Output Image ---
imagepng($image);
imagedestroy($image);
exit;
