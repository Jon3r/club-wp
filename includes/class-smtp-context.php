<?php
/**
 * Per-request SMTP settings when sending mail for a specific Clubworx location.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Clubworx_SMTP_Context {

    /** @var array<string,mixed>|null */
    private static $smtp = null;

    /** @var bool */
    private static $active = false;

    /**
     * Run callback with location SMTP bound for phpmailer_init.
     *
     * @param array<string,mixed> $location Normalized location array.
     * @param callable():mixed $fn
     * @return mixed
     */
    public static function with_location($location, $fn) {
        self::$smtp = isset($location['smtp']) && is_array($location['smtp']) ? $location['smtp'] : array();
        self::$active = true;
        try {
            return call_user_func($fn);
        } finally {
            self::$smtp = null;
            self::$active = false;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get_smtp() {
        return self::$smtp;
    }

    public static function is_active() {
        return self::$active;
    }
}
