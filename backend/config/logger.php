<?php
// backend/config/logger.php

class Logger {
    private static $logDirectory = '../logs/'; // Default directory
    
    public static function log($message, $level = 'info', $category = 'app') {
        // Ensure directories exist
        $logDir = self::$logDirectory . $category . '/';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Build log file name with date format
        $logFile = $logDir . date('Y-m-d') . '.log';
        
        // Format the log message
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        // Write to log file
        file_put_contents($logFile, $formattedMessage, FILE_APPEND);
        
        // Output to console if in debug mode
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            echo "<pre>{$formattedMessage}</pre>";
        }
    }
    
    public static function error($message, $category = 'errors') {
        self::log($message, 'ERROR', $category);
    }
    
    public static function warning($message, $category = 'app') {
        self::log($message, 'WARNING', $category);
    }
    
    public static function info($message, $category = 'app') {
        self::log($message, 'INFO', $category);
    }
    
    public static function debug($message, $category = 'debug') {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            self::log($message, 'DEBUG', $category);
        }
    }

    public static function setLogDirectory($directory) {
        self::$logDirectory = rtrim($directory, '/') . '/';
    }
}

define('DEBUG_MODE', true);