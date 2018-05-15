<?php
/* Logger.php - Simple logging utility for PNM Callbacks */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PnmLogger {
    const INFO = 0;
    const DEBUG = 1;
    const WARN = 2;
    const ERROR = 3;

    static $log_level = self::INFO;

    static function debug($message) {
        self::log(self::DEBUG, $message);
    }

    static function warn($message) {
        self::log(self::WARN, $message);
    }

    static function error($message) {
        self::log(self::ERROR, $message);
    }

    static function info($message) {
        self::log(self::INFO, $message);
    }

    static function log($level, $message) {
        if ($level >= self::$log_level) {
            $str = sprintf("[PNM] %s - %s", self::level_name($level), $message);
            error_log($str);
        }
    }

    private static function level_name($level) {
        switch ($level) {
            case self::INFO:
            return 'INFO';
            case self::DEBUG:
            return 'DEBUG';
            case self::WARN:
            return 'WARN';
            case self::ERROR:
            return 'ERROR';
            default:
            return 'UNKNOWN';
        }
    }
}
