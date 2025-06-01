<?php
declare(strict_types=1);

namespace OmniSIS\Core;

use InvalidArgumentException;
use RuntimeException;

/**
 * A very simple single‐channel file‐based logger.
 * Writes one key‐value record per line to storage/logs/app.log.
 *
 * DEBUG messages are added at every possible step.
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
        // Log before deciding whether to create instance
        if (self::$instance === null) {
            // We can’t log _into_ the logger yet, so let’s write directly to stderr
            file_put_contents('php://stderr', "[Logger DEBUG] getInstance: instance is null; creating new Logger()\n");
            self::$instance = new Logger();
        } else {
            file_put_contents('php://stderr', "[Logger DEBUG] getInstance: returning existing instance\n");
        }
        return self::$instance;
    }

    /**
     * Private constructor. Opens the log file in append mode.
     * Throws if path is not writable or cannot be created.
     */
    private function __construct()
    {
        file_put_contents('php://stderr', "[Logger DEBUG] __construct: enter\n");

        // Determine the absolute path to storage/logs/app.log
        $baseDir = dirname(__DIR__, 1); // project-root/app/Core → project-root
        $this->filePath = $baseDir . self::LOG_FILE_RELATIVE;
        file_put_contents('php://stderr', "[Logger DEBUG] __construct: computed filePath = {$this->filePath}\n");

        // Ensure the directory exists
        $dir = dirname($this->filePath);
        file_put_contents('php://stderr', "[Logger DEBUG] __construct: checking/creating directory {$dir}\n");
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $msg = "Unable to create log directory: {$dir}";
            file_put_contents('php://stderr', "[Logger ERROR] __construct: {$msg}\n");
            throw new RuntimeException($msg);
        }

        // Open (or create) the file in append mode
        file_put_contents('php://stderr', "[Logger DEBUG] __construct: fopen('{$this->filePath}', 'a')\n");
        $this->handle = @fopen($this->filePath, 'a');
        if ($this->handle === false) {
            $msg = "Unable to open log file: {$this->filePath}";
            file_put_contents('php://stderr', "[Logger ERROR] __construct: {$msg}\n");
            throw new RuntimeException($msg);
        }

        // Set UTF-8 encoding if needed (optional)
        // file_put_contents('php://stderr', "[Logger DEBUG] __construct: setting UTF-8 encoding if required\n");
        // stream_encoding($this->handle, 'utf-8'); // PHP 8.2+
    }

    /**
     * Close the file handle on destruction.
     */
    public function __destruct()
    {
        file_put_contents('php://stderr', "[Logger DEBUG] __destruct: entering\n");
        if (is_resource($this->handle)) {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: fclose(handle)\n");
            fclose($this->handle);
        } else {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: handle is not a resource\n");
        }
    }

    /**
     * Log an INFO-level message.
     *
     * @param string $message
     * @param array<string,mixed> $context  Additional key/value pairs
     */
    public function info(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a DEBUG-level message.
     */
    public function debug(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log a WARN-level message.
     */
    public function warn(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_WARN, $message, $context);
    }

    /**
     * Log an ERROR-level message.
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
        // 1) Build timestamp
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
        file_put_contents('php://stderr', "[Logger DEBUG] write: timestamp={$now}, level={$level}, message={$message}\n");

        // 2) Build the core line as key=value pairs
        $parts = [
            "timestamp={$now}",
            "level={$level}",
            "message=" . $this->escapeValue($message),
        ];
        file_put_contents('php://stderr', "[Logger DEBUG] write: initial parts = " . implode(' | ', $parts) . "\n");

        // 3) Append context key/value
        foreach ($context as $key => $val) {
            file_put_contents('php://stderr', "[Logger DEBUG] write: sanitizing key '{$key}' and value '".(string)$val."'\n");
            $sanitizedKey = $this->sanitizeKey($key);
            $escapedVal   = $this->escapeValue((string)$val);
            $parts[]      = "{$sanitizedKey}={$escapedVal}";
            file_put_contents('php://stderr', "[Logger DEBUG] write: appended '{$sanitizedKey}={$escapedVal}'\n");
        }

        // 4) Join into one line
        $line = implode(' ', $parts) . PHP_EOL;
        file_put_contents('php://stderr', "[Logger DEBUG] write: final line = {$line}\n");

        // 5) Acquire lock, write, release
        if (flock($this->handle, LOCK_EX)) {
            file_put_contents('php://stderr', "[Logger DEBUG] write: flock acquired, writing\n");
            fwrite($this->handle, $line);
            fflush($this->handle);
            flock($this->handle, LOCK_UN);
            file_put_contents('php://stderr', "[Logger DEBUG] write: flock released after writing\n");
        } else {
            file_put_contents('php://stderr', "[Logger WARN] write: could not acquire flock, dropping log\n");
            // Dropping to avoid blocking
        }
    }

    /**
     * Ensure the key contains only alphanumeric or underscore.
     */
    private function sanitizeKey(string $key): string
    {
        file_put_contents('php://stderr', "[Logger DEBUG] sanitizeKey: checking '{$key}'\n");
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
            file_put_contents('php://stderr', "[Logger ERROR] sanitizeKey: invalid key '{$key}'\n");
            throw new InvalidArgumentException("Invalid context key for logger: {$key}");
        }
        return $key;
    }

    /**
     * Escape value by wrapping in quotes if needed, and escaping inner quotes/backslashes.
     */
    private function escapeValue(string $value): string
    {
        file_put_contents('php://stderr', "[Logger DEBUG] escapeValue: raw value = {$value}\n");
        $escaped = addcslashes($value, "\\\"");
        file_put_contents('php://stderr', "[Logger DEBUG] escapeValue: escaped value = {$escaped}\n");
        return '"' . $escaped . '"';
    }
}
