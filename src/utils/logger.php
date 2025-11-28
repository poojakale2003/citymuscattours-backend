<?php

class Logger {
    public static function info($message, $context = []) {
        $log = date('Y-m-d H:i:s') . " [INFO] {$message}";
        if (!empty($context)) {
            $log .= " " . json_encode($context);
        }
        error_log($log);
    }

    public static function error($message, $context = []) {
        $log = date('Y-m-d H:i:s') . " [ERROR] {$message}";
        if (!empty($context)) {
            $log .= " " . json_encode($context);
        }
        error_log($log);
    }

    public static function warn($message, $context = []) {
        $log = date('Y-m-d H:i:s') . " [WARN] {$message}";
        if (!empty($context)) {
            $log .= " " . json_encode($context);
        }
        error_log($log);
    }
}

