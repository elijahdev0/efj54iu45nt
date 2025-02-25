<?php

namespace FSPoster\App\Providers\Helpers;

use FSPoster\App\Providers\Core\Settings;

class Logger {
    private static string $filePath;

    private static function init() {
        $loggerStartAt = (int) Settings::get('logger_started_at');

        if (!$loggerStartAt) return;

        $loggerFileName = md5($loggerStartAt) . '.log';

        if (!isset(self::$filePath)) {
            self::$filePath = __DIR__ . '/' . $loggerFileName;
        }
        if (!file_exists(self::$filePath)) {
            self::createLogFile();
        }
    }

    private static function log(string $message, string $type = 'LOG', string $dataType = 'STRING', $data = null) {
        self::init();
        if (!is_writable(dirname(self::$filePath))) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $formattedData = self::formatData($data);
        $logEntry = "[$timestamp] [$type] [$dataType] $formattedData" . PHP_EOL;

        file_put_contents(self::$filePath, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public static function info($data) {
        self::log('INFO', 'INFO', gettype($data), $data);
    }

    public static function error($data) {
        self::log('ERROR', 'ERROR', gettype($data), $data);
    }

    public static function warn($data) {
        self::log('WARNING', 'WARN', gettype($data), $data);
    }

    private static function formatData($data) {
        if (is_array($data) || is_object($data)) {
            return json_encode($data, JSON_PRETTY_PRINT);
        }
        return (string) $data;
    }

    private static function createLogFile() {
        if (!is_writable(dirname(self::$filePath))) {
            return;
        }

        file_put_contents(self::$filePath, "", LOCK_EX);
    }

    public static function getContent() {
        self::init();
        if (!file_exists(self::$filePath)) {
            return '';
        }

        return file_get_contents(self::$filePath);
    }

    public static function delete() {
        self::init();
        if (file_exists(self::$filePath) && is_writable(self::$filePath)) {
            unlink(self::$filePath);

            self::$filePath = "";
        }
    }
}
