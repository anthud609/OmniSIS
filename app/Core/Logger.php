<?php
declare(strict_types=1);

namespace OmniSIS\Core;

use InvalidArgumentException;
use RuntimeException;

/**
 * A very simple single‐channel file‐based logger.
 * Writes one key‐value record per line to storage/logs/app.log.
 *
 * Usage:
 *   $logger = Logger::getInstance(); // singleton
 *   $logger->info('User logged in', ['user_id' => 42, 'ip' => '127.0.0.1']);
 */
final class Logger
{
    private const LOG_FILE_RELATIVE = '/../storage/logs/app.log';

    private const LEVEL_INFO  = 'INFO';
    private const LEVEL_DEBUG = 'DEBUG';
    private const LEVEL_WARN  = 'WARN';
    private const LEVEL_ERROR = 'ERROR';

    private string $filePath;
    private $handle;

    /** @var Logger|null */
    private static ?Logger $instance = null;

    /**
     * Get the singleton instance of Logger.
     */
    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            self::$instance = new Logger();
        }
        return self::$instance;
    }

    /**
     * Private constructor. Opens the log file in append mode.
     * Throws if path is not writable or cannot be created.
     */
    private function __construct()
    {
        // Determine the absolute path to storage/logs/app.log
        $baseDir = dirname(__DIR__, 1); // if this file is core/Logger.php, then dirname(__DIR__) is project root
        $this->filePath = $baseDir . self::LOG_FILE_RELATIVE;

        // Ensure the directory exists
        $dir = dirname($this->filePath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("Unable to create log directory: {$dir}");
        }

        // Open (or create) the file in append mode
        $this->handle = @fopen($this->filePath, 'a');
        if ($this->handle === false) {
            throw new RuntimeException("Unable to open log file: {$this->filePath}");
        }

        // Set UTF‐8 encoding if needed (optional)
        // stream_encoding($this->handle, 'utf-8'); // if using php 8.2+; otherwise ensure text is UTF‐8 before writing
    }

    /**
     * Close the file handle on destruction.
     */
    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /**
     * Log an INFO‐level message.
     *
     * @param string $message
     * @param array<string,mixed> $context  Additional key/value pairs (e.g. ['user_id'=>123])
     */
    public function info(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a DEBUG‐level message.
     */
    public function debug(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log a WARN‐level message.
     */
    public function warn(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_WARN, $message, $context);
    }

    /**
     * Log an ERROR‐level message.
     */
    public function error(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Write a line to the log. Always atomic via flock() lock.
     */
    private function write(string $level, string $message, array $context = []): void
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');

        // Build the core line as key=value pairs
        $parts = [
            "timestamp={$now}",
            "level={$level}",
            "message=" . $this->escapeValue($message),
        ];

        foreach ($context as $key => $val) {
            $parts[] = $this->sanitizeKey($key) . '=' . $this->escapeValue((string)$val);
        }

        $line = implode(' ', $parts) . PHP_EOL;

        // Acquire exclusive lock, write, then release
        if (flock($this->handle, LOCK_EX)) {
            fwrite($this->handle, $line);
            fflush($this->handle);
            flock($this->handle, LOCK_UN);
        } else {
            // If we can't lock, throw or silently drop? We choose to drop to avoid breaking the app.
            // Alternatively, uncomment the next line to throw:
            // throw new RuntimeException('Could not lock the log file for writing.');
        }
    }

    /**
     * Ensure the key contains only alphanumeric or underscore.
     */
    private function sanitizeKey(string $key): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
            throw new InvalidArgumentException("Invalid context key for logger: {$key}");
        }
        return $key;
    }

    /**
     * Escape value by wrapping in quotes if needed, and escaping inner quotes/backslashes.
     */
    private function escapeValue(string $value): string
    {
        // Replace backslashes and quotes
        $escaped = addcslashes($value, "\\\"");
        return '"' . $escaped . '"';
    }
}
