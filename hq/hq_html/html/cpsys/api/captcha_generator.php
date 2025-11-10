<?php
/**
 * Toptea HQ - cpsys
 * Simple CAPTCHA Image Generator
 * Engineer: Gemini | Date: 2025-10-24
 *
 * [GEMINI SECURITY FIX V1.2 - 2025-11-10]
 * - Repaired file encoding by removing non-breaking space characters (U+00A0)
 * that were causing a fatal Parse Error.
 */

@session_start();

// --- Configuration ---
$width = 120;
$height = 40;
$length = 4; // Number of characters
$font_size = 20;
$characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // Avoid confusing characters like 1, I, 0, O

// --- Generate Code ---
$code = '';
for ($i = 0; $i < $length; $i++) {
    $code .= $characters[rand(0, strlen($characters) - 1)];
}

// Store the code in the session, case-insensitive
$_SESSION['captcha_code'] = strtolower($code);

// --- Create Image ---
$image = imagecreatetruecolor($width, $height);
$bg_color = imagecolorallocate($image, 33, 37, 41); // Dark background
$text_color = imagecolorallocate($image, 237, 119, 98); // Brand color for text
$line_color = imagecolorallocate($image, 60, 65, 70); // Muted lines

imagefilledrectangle($image, 0, 0, $width, $height, $bg_color);

// Add some random noise (lines)
for ($i = 0; $i < 5; $i++) {
    imageline($image, 0, rand() % $height, $width, rand() % $height, $line_color);
}

// Add the text
// You might need to provide a path to a .ttf font file on your server.
// If you don't have one, this might fail or look bad. Let's use a built-in font as a fallback.
$font = 5; // Built-in GD font
$x = ($width - imagefontwidth($font) * $length) / 2;
$y = ($height - imagefontheight($font)) / 2;
imagestring($image, $font, $x, $y, $code, $text_color);


// --- Output Image ---
header('Content-Type: image/png');
header('Cache-Control: no-cache, must-revalidate'); // Prevent caching
imagepng($image);
imagedestroy($image);