<?php
/**
 * Toptea HQ - CPSYS 平台
 * 统一时间助手 (A1 UTC SYNC)
 * 职责: 提供 UTC 时间转换和本地化格式化功能。
 *
 * - utc_now(): 获取当前的 UTC DateTime 对象。
 * - to_utc_window(): 将本地日期范围转换为 UTC DateTime 范围。
 * - fmt_local(): 将 UTC 时间格式化为本地时间字符串。
 *
 * 依赖: APP_DEFAULT_TIMEZONE (在 helpers/kds_helper.php 中定义)
 */

declare(strict_types=1);

// 默认时区兜底（若上层未定义）
if (!defined('APP_DEFAULT_TIMEZONE')) {
    define('APP_DEFAULT_TIMEZONE', 'Europe/Madrid');
}

/** 获取当前 UTC 时间 */
if (!function_exists('utc_now')) {
    function utc_now(): DateTime {
        return new DateTime('now', new DateTimeZone('UTC'));
    }
}

/**
 * 将 UTC 时间格式化为本地时区字符串
 *
 * @param string|DateTime|null $utc_datetime  UTC 时间（字符串或 DateTime）
 * @param string               $format        输出格式（默认 Y-m-d H:i:s）
 * @param string               $timezone      目标时区（默认 Europe/Madrid）
 * @return string|null
 */
if (!function_exists('fmt_local')) {
    function fmt_local($utc_datetime, string $format = 'Y-m-d H:i:s', string $timezone = APP_DEFAULT_TIMEZONE): ?string {
        if ($utc_datetime === null || $utc_datetime === '') {
            return null;
        }
        try {
            $dt = ($utc_datetime instanceof DateTime)
                ? clone $utc_datetime
                : new DateTime((string)$utc_datetime, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone($timezone));
            return $dt->format($format);
        } catch (Throwable $e) {
            error_log('fmt_local error: ' . $e->getMessage());
            return null;
        }
    }
}

/**
 * 将本地日期范围（闭区间日窗）转换为 UTC DateTime 起止
 * - $local_date_to 为空 => 单日 00:00:00 ~ 23:59:59.999999
 * - 返回的两个 DateTime 都为 UTC 时区
 *
 * @return array{0:DateTime,1:DateTime}
 */
if (!function_exists('to_utc_window')) {
    function to_utc_window(string $local_date_from, ?string $local_date_to = null, string $timezone = APP_DEFAULT_TIMEZONE): array {
        $tz_local = new DateTimeZone($timezone);
        $tz_utc   = new DateTimeZone('UTC');
        try {
            $start_local = new DateTime($local_date_from . ' 00:00:00', $tz_local);
            if ($local_date_to === null || $local_date_to === $local_date_from) {
                $end_local = new DateTime($local_date_from . ' 23:59:59.999999', $tz_local);
            } else {
                $end_local = new DateTime($local_date_to . ' 23:59:59.999999', $tz_local);
            }
            $start = (clone $start_local)->setTimezone($tz_utc);
            $end   = (clone $end_local)->setTimezone($tz_utc);
            return [$start, $end];
        } catch (Throwable $e) {
            error_log('to_utc_window error: ' . $e->getMessage());
            // 兜底：返回当天 UTC 日窗
            $today = new DateTime('now', $tz_local);
            $start_fallback = (clone $today)->setTime(0, 0, 0)->setTimezone($tz_utc);
            $end_fallback   = (clone $today)->setTime(23, 59, 59)->setTimezone($tz_utc);
            return [$start_fallback, $end_fallback];
        }
    }
}
