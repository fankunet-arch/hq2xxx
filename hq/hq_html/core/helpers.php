<?php
// core/helpers.php

/**
 * 获取当前时间的 UTC 格式化字符串 (DATETIME(6) 格式)
 * * 这是方案的核心。
 * 所有 PHP 代码在 INSERT 或 UPDATE 记录时，
 * 都必须*主动*使用此函数来生成时间戳。
 *
 * @return string
 */
function get_utc_now_string(): string
{
    // 创建一个 DateTime 对象，时区强制为 UTC
    $datetime_utc = new DateTime("now", new DateTimeZone("UTC"));
    
    // 格式化为 MySQL DATETIME(6) 兼容的字符串 (Y-m-d H:i:s.u)
    return $datetime_utc->format('Y-m-d H:i:s.u');
}

/**
 * 将本地时间字符串（例如来自前端输入）转换为 UTC 字符串
 *
 * @param string $local_datetime_string (例如: '2025-11-11 14:30:00')
 * @param string $local_timezone (例如: 'Europe/Madrid')
 * @return string
 * @throws Exception
 */
function convert_local_to_utc_string(string $local_datetime_string, string $local_timezone = 'Europe/Madrid'): string
{
    // 1. 创建一个代表本地时间的对象
    $local_time = new DateTime($local_datetime_string, new DateTimeZone($local_timezone));
    
    // 2. 将该对象转换为 UTC 时区
    $local_time->setTimezone(new DateTimeZone("UTC"));
    
    // 3. 格式化为 MySQL 字符串
    return $local_time->format('Y-m-d H:i:s.u');
}

/**
 * 将 UTC 时间字符串（从数据库读取）转换为本地时间字符串（用于显示）
 *
 * @param string $utc_datetime_string (从数据库读取: '2025-11-11 10:30:00.123456')
 * @param string $local_timezone (例如: 'Europe/Madrid')
 * @param string $format (例如: 'Y-m-d H:i')
 * @return string
 * @throws Exception
 */
function convert_utc_to_local_string(string $utc_datetime_string, string $local_timezone = 'Europe/Madrid', string $format = 'Y-m-d H:i:s'): string
{
    // 1. 创建一个代表 UTC 时间的对象
    $utc_time = new DateTime($utc_datetime_string, new DateTimeZone("UTC"));
    
    // 2. 将该对象转换为本地时区
    $utc_time->setTimezone(new DateTimeZone($local_timezone));
    
    // 3. 格式化为显示字符串
    return $utc_time->format($format);
}