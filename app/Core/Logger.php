<?php
declare(strict_types=1);

namespace OmniSIS\Core;

use InvalidArgumentException;
use RuntimeException;

/**
 * A very simple multi‐file logger.
 *  - storage/logs/app.log: all levels EXCEPT DEBUG
 *  - storage/logs/debug.log: all levels, including DEBUG (only if APP_DEBUG=true)
 *
 * Whether DEBUG‐level messages are written is controlled by the APP_DEBUG environment variable.
 */
final class Logger
{
    private const APP_LOG_FILE_RELATIVE   = '/../storage/logs/app.log';
    private const DEBUG_LOG_FILE_RELATIVE = '/../storage/logs/debug.log';

    private const LEVEL_INFO  = 'INFO';
    private const LEVEL_DEBUG = 'DEBUG';
    private const LEVEL_WARN  = 'WARN';
    private const LEVEL_ERROR = 'ERROR';

    private string   $appFilePath;
    private string   $debugFilePath;
    private $appHandle;
    private $debugHandle;
    private bool     $debugEnabled;

    /** @var Logger|null */
    private static ?Logger $instance = null;

    /**
     * Get the singleton instance of Logger.
     */
    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            // We can’t log _into_ the logger yet, so write directly to stderr
            file_put_contents('php://stderr', "[Logger DEBUG] getInstance: instance is null; creating new Logger()\n");
            self::$instance = new Logger();
        } else {
            file_put_contents('php://stderr', "[Logger DEBUG] getInstance: returning existing instance\n");
        }
        return self::$instance;
    }

    /**
     * Private constructor. Opens both log files in append mode.
     * Throws if paths are not writable or cannot be created.
     */
    private function __construct()
    {
        file_put_contents('php://stderr', "[Logger DEBUG] __construct: enter\n");

        // Determine whether DEBUG is enabled (APP_DEBUG env)
        // Filter “true”, “1”, “yes” → true; else false
        $this->debugEnabled = filter_var(
            getenv('APP_DEBUG') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        );
        file_put_contents(
            'php://stderr',
            "[Logger DEBUG] __construct: debugEnabled = " . ($this->debugEnabled ? 'true' : 'false') . "\n"
        );

        // Determine absolute paths for both logs
        $baseDir = dirname(__DIR__, 1); // project-root/app/Core → project-root
        $this->appFilePath   = $baseDir . self::APP_LOG_FILE_RELATIVE;
        $this->debugFilePath = $baseDir . self::DEBUG_LOG_FILE_RELATIVE;

        file_put_contents('php://stderr', "[Logger DEBUG] __construct: computed appFilePath   = {$this->appFilePath}\n");
        file_put_contents('php://stderr', "[Logger DEBUG] __construct: computed debugFilePath = {$this->debugFilePath}\n");

        // Ensure directories exist for both files
        $appDir   = dirname($this->appFilePath);
        $debugDir = dirname($this->debugFilePath);

        foreach ([$appDir, $debugDir] as $dir) {
            file_put_contents('php://stderr', "[Logger DEBUG] __construct: checking/creating directory {$dir}\n");
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                $msg = "Unable to create log directory: {$dir}";
                file_put_contents('php://stderr', "[Logger ERROR] __construct: {$msg}\n");
                throw new RuntimeException($msg);
            }
        }

        // Open (or create) both files in append mode
        file_put_contents('php://stderr', "[Logger DEBUG] __construct: fopen('{$this->appFilePath}', 'a')\n");
        $this->appHandle = @fopen($this->appFilePath, 'a');
        if ($this->appHandle === false) {
            $msg = "Unable to open app log file: {$this->appFilePath}";
            file_put_contents('php://stderr', "[Logger ERROR] __construct: {$msg}\n");
            throw new RuntimeException($msg);
        }

        file_put_contents('php://stderr', "[Logger DEBUG] __construct: fopen('{$this->debugFilePath}', 'a')\n");
        $this->debugHandle = @fopen($this->debugFilePath, 'a');
        if ($this->debugHandle === false) {
            $msg = "Unable to open debug log file: {$this->debugFilePath}";
            file_put_contents('php://stderr', "[Logger ERROR] __construct: {$msg}\n");
            throw new RuntimeException($msg);
        }
    }

    /**
     * Close the file handles on destruction.
     */
    public function __destruct()
    {
        file_put_contents('php://stderr', "[Logger DEBUG] __destruct: entering\n");

        if (is_resource($this->appHandle)) {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: fclose(appHandle)\n");
            fclose($this->appHandle);
        } else {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: appHandle is not a resource\n");
        }

        if (is_resource($this->debugHandle)) {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: fclose(debugHandle)\n");
            fclose($this->debugHandle);
        } else {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: debugHandle is not a resource\n");
        }
    }

    /**
     * Log an INFO-level message.
     *
     * @param string               $message
     * @param array<string,mixed>  $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log a DEBUG-level message.
     * Respects $this->debugEnabled.
     */
    public function debug(string $message, array $context = []): void
    {
        if (! $this->debugEnabled) {
            // APP_DEBUG is false → skip all debug-level writes
            return;
        }
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
     * Write a line to the appropriate log files. Always atomic via flock().
     *
     * - DEBUG → only debug.log (unless APP_DEBUG was false, in which case debug() never calls write())
     * - INFO/WARN/ERROR → both app.log and debug.log
     */
    private function write(string $level, string $message, array $context = []): void
    {
        // 1) Build timestamp
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
        file_put_contents('php://stderr', "[Logger DEBUG] write: timestamp={$now}, level={$level}, message={$message}\n");

        // 2) Build core line as key=value pairs
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

        // 5) Always write to debug.log (since non-debug levels also go there)
        if (flock($this->debugHandle, LOCK_EX)) {
            file_put_contents('php://stderr', "[Logger DEBUG] write: flock acquired on debugHandle, writing\n");
            fwrite($this->debugHandle, $line);
            fflush($this->debugHandle);
            flock($this->debugHandle, LOCK_UN);
            file_put_contents('php://stderr', "[Logger DEBUG] write: flock released on debugHandle\n");
        } else {
            file_put_contents('php://stderr', "[Logger WARN] write: could not acquire flock on debugHandle, dropping debug log\n");
        }

        // 6) For INFO/WARN/ERROR only, also write to app.log (unchanged logic)
        if ($level !== self::LEVEL_DEBUG) {
            if (flock($this->appHandle, LOCK_EX)) {
                file_put_contents('php://stderr', "[Logger DEBUG] write: flock acquired on appHandle, writing\n");
                fwrite($this->appHandle, $line);
                fflush($this->appHandle);
                flock($this->appHandle, LOCK_UN);
                file_put_contents('php://stderr', "[Logger DEBUG] write: flock released on appHandle\n");
            } else {
                file_put_contents('php://stderr', "[Logger WARN] write: could not acquire flock on appHandle, dropping app log\n");
            }
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
