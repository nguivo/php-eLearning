<?php

declare(strict_types=1);

/**
 * App/Helpers/functions.php — Global Helper Functions
 *
 * Plain stateless functions available everywhere in the application —
 * controllers, services, views, middleware — without any import or injection.
 *
 * Loaded automatically by Composer via the "files" autoload key in composer.json.
 * Run `composer dump-autoload` once after adding this file.
 *
 * Rules:
 *   - Functions here have no dependencies, no state, no side effects.
 *   - They take input and return output. That is all.
 *   - Nothing here talks to the database, reads the session, or touches HTTP.
 *   - If a function needs $this, injection, or extension — it is a class, not a helper.
 */


// =============================================================================
// OUTPUT & ESCAPING
// =============================================================================

/**
 * Escape a string for safe HTML output. Use this on ALL user-supplied data
 * printed in views to prevent XSS attacks.
 *
 * Usage in views:
 *   <h1><?= e($course->title) ?></h1>
 *   <p><?= e($user->bio) ?></p>
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


/**
 * Escape a string for safe output inside an HTML attribute value.
 * Use this when building attribute values dynamically.
 *
 * Usage:
 *   <input value="<?= attr($course->title) ?>">
 *   <div data-id="<?= attr($id) ?>">
 */
function attr(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


// =============================================================================
// NUMBER & CURRENCY
// =============================================================================

/**
 * Format a price for display.
 *
 * Usage:
 *   formatPrice(29.99)          → '$29.99'
 *   formatPrice(0)              → 'Free'
 *   formatPrice(199.00, 'GBP')  → '£199.00'
 *
 * @param float  $amount   The price to format
 * @param string $currency Currency code: 'USD', 'EUR', 'GBP'
 */
function formatPrice(float $amount, string $currency = 'USD'): string
{
    if ($amount <= 0) {
        return 'Free';
    }

    $symbol = match (strtoupper($currency)) {
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        default => strtoupper($currency) . ' ',
    };

    return $symbol . number_format($amount, 2);
}


/**
 * Format a duration in seconds into a human-readable MM:SS or HH:MM:SS string.
 *
 * Used for displaying video lesson lengths.
 *
 * Usage:
 *   formatDuration(125)   → '2:05'
 *   formatDuration(3661)  → '1:01:01'
 *   formatDuration(45)    → '0:45'
 *
 * @param int $seconds Total duration in seconds
 */
function formatDuration(int $seconds): string
{
    $seconds = max(0, $seconds);
    $hours   = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs    = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    }

    return sprintf('%d:%02d', $minutes, $secs);
}


/**
 * Format a duration in seconds into a long-form readable string.
 *
 * Used for course total length displays.
 *
 * Usage:
 *   formatDurationLong(3661)   → '1 hour 1 minute'
 *   formatDurationLong(7200)   → '2 hours'
 *   formatDurationLong(125)    → '2 minutes'
 *   formatDurationLong(45)     → '45 seconds'
 *
 * @param int $seconds Total duration in seconds
 */
function formatDurationLong(int $seconds): string
{
    $seconds = max(0, $seconds);
    $hours   = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs    = $seconds % 60;

    $parts = [];

    if ($hours > 0) {
        $parts[] = $hours . ' hour' . ($hours !== 1 ? 's' : '');
    }

    if ($minutes > 0) {
        $parts[] = $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
    }

    if ($secs > 0 && $hours === 0) {
        $parts[] = $secs . ' second' . ($secs !== 1 ? 's' : '');
    }

    return implode(' ', $parts) ?: '0 seconds';
}


/**
 * Format a file size in bytes into a human-readable string.
 *
 * Used when displaying uploaded video or document sizes.
 *
 * Usage:
 *   formatBytes(1024)         → '1 KB'
 *   formatBytes(1048576)      → '1 MB'
 *   formatBytes(1073741824)   → '1 GB'
 *   formatBytes(500)          → '500 B'
 *
 * @param int $bytes     File size in bytes
 * @param int $precision Decimal places (default 2)
 */
function formatBytes(int $bytes, int $precision = 2): string
{
    $bytes = max(0, $bytes);

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $index = 0;
    $size  = (float) $bytes;

    while ($size >= 1024 && $index < count($units) - 1) {
        $size /= 1024;
        $index++;
    }

    return round($size, $precision) . ' ' . $units[$index];
}


/**
 * Format a number with thousands separators.
 *
 * Usage:
 *   formatNumber(1234567)  → '1,234,567'
 *   formatNumber(9999.5)   → '9,999.50'
 *
 * @param float $number    The number to format
 * @param int   $decimals  Number of decimal places
 */
function formatNumber(float $number, int $decimals = 0): string
{
    return number_format($number, $decimals, '.', ',');
}


// =============================================================================
// DUMP AND DIE
// =============================================================================
function dnd(mixed $data): void
{
    echo "<pre>";
    var_dump($data);
    echo "</pre>";
    exit;
}
