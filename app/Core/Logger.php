<?php
declare(strict_types=1);

namespace OmniSIS\Core;

use InvalidArgumentException;
use RuntimeException;

final class Logger
{
    private const APP_LOG_FILE_RELATIVE   = '/../storage/logs/app.log';
    private const DEBUG_LOG_FILE_RELATIVE = '/../storage/logs/debug.log';
    private const ERROR_LOG_FILE_RELATIVE = '/../storage/logs/error.log';

    private const LEVEL_INFO  = 'INFO';
    private const LEVEL_DEBUG = 'DEBUG';
    private const LEVEL_WARN  = 'WARN';
    private const LEVEL_ERROR = 'ERROR';

    private string   $appFilePath;
    private ?string  $debugFilePath = null;
    private string   $errorFilePath;
    private $appHandle;
    private $debugHandle;   // null if debug disabled
    private $errorHandle;
    private bool     $debugEnabled;

    private static ?Logger $instance = null;

    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            // Before constructing, we can’t log to our own logger yet:
            file_put_contents('php://stderr', "[Logger DEBUG] getInstance: creating new Logger()\n");
            self::$instance = new Logger();
        } else {
            file_put_contents('php://stderr', "[Logger DEBUG] getInstance: reusing existing instance\n");
        }
        return self::$instance;
    }

    private function __construct()
    {
        file_put_contents('php://stderr', "[Logger DEBUG] __construct: entering\n");

        //
        // ─── 1) READ APP_DEBUG FROM ENVIRONMENT ────────────────────────────────────
        //
        // Try getenv first; if that's empty, fall back to $_ENV['APP_DEBUG'].
        // Default to 'false' if neither is set.
        //
        $rawEnv = getenv('APP_DEBUG');
        if ($rawEnv === false || trim($rawEnv) === '') {
            // Maybe PHP-FPM or Apache has it in $_ENV but not in getenv()
            $rawEnv = $_ENV['APP_DEBUG'] ?? 'false';
        }
        // FILTER_VALIDATE_BOOLEAN maps "true","1","yes" → true; otherwise false
        $this->debugEnabled = filter_var($rawEnv, FILTER_VALIDATE_BOOLEAN);
        file_put_contents(
            'php://stderr',
            "[Logger DEBUG] __construct: debugEnabled = " . ($this->debugEnabled ? 'true' : 'false') . "\n"
        );

        //
        // ─── 2) COMPUTE ABSOLUTE PATHS ────────────────────────────────────────────
        //
        $baseDir = dirname(__DIR__, 1); // project-root/app/Core → project-root
        $this->appFilePath   = $baseDir . self::APP_LOG_FILE_RELATIVE;
        $this->errorFilePath = $baseDir . self::ERROR_LOG_FILE_RELATIVE;
        if ($this->debugEnabled) {
            $this->debugFilePath = $baseDir . self::DEBUG_LOG_FILE_RELATIVE;
        }

        file_put_contents('php://stderr', "[Logger DEBUG] __construct: appFilePath   = {$this->appFilePath}\n");
        file_put_contents('php://stderr', "[Logger DEBUG] __construct: errorFilePath = {$this->errorFilePath}\n");
        if ($this->debugEnabled) {
            file_put_contents('php://stderr', "[Logger DEBUG] __construct: debugFilePath = {$this->debugFilePath}\n");
        } else {
            file_put_contents('php://stderr', "[Logger DEBUG] __construct: debugFilePath disabled (APP_DEBUG=false)\n");
        }

        //
        // ─── 3) ENSURE LOG DIRECTORIES EXIST ──────────────────────────────────────
        //
        $appDir   = dirname($this->appFilePath);
        $errorDir = dirname($this->errorFilePath);
        $dirs     = [$appDir, $errorDir];

        if ($this->debugEnabled && $this->debugFilePath !== null) {
            $dirs[] = dirname($this->debugFilePath);
        }

        foreach ($dirs as $dir) {
            file_put_contents('php://stderr', "[Logger DEBUG] __construct: checking/creating directory {$dir}\n");
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                $msg = "Unable to create log directory: {$dir}";
                file_put_contents('php://stderr', "[Logger ERROR] __construct: {$msg}\n");
                throw new RuntimeException($msg);
            }
        }

        //
        // ─── 4) OPEN app.log ───────────────────────────────────────────────────────
        //
        file_put_contents('php://stderr', "[Logger DEBUG] __construct: fopen('{$this->appFilePath}', 'a')\n");
        $this->appHandle = @fopen($this->appFilePath, 'a');
        if ($this->appHandle === false) {
            $msg = "Unable to open app log file: {$this->appFilePath}";
            file_put_contents('php://stderr', "[Logger ERROR] __construct: {$msg}\n");
            throw new RuntimeException($msg);
        }

        //
        // ─── 5) OPEN debug.log ONLY IF ENABLED ────────────────────────────────────
        //
        if ($this->debugEnabled && $this->debugFilePath !== null) {
            file_put_contents('php://stderr', "[Logger DEBUG] __construct: fopen('{$this->debugFilePath}', 'a')\n");
            $this->debugHandle = @fopen($this->debugFilePath, 'a');
            if ($this->debugHandle === false) {
                $msg = "Unable to open debug log file: {$this->debugFilePath}";
                file_put_contents('php://stderr', "[Logger ERROR] __construct: {$msg}\n");
                throw new RuntimeException($msg);
            }
        } else {
            $this->debugHandle = null;
        }

        //
        // ─── 6) OPEN error.log ─────────────────────────────────────────────────────
        //
        file_put_contents('php://stderr', "[Logger DEBUG] __construct: fopen('{$this->errorFilePath}', 'a')\n");
        $this->errorHandle = @fopen($this->errorFilePath, 'a');
        if ($this->errorHandle === false) {
            $msg = "Unable to open error log file: {$this->errorFilePath}";
            file_put_contents('php://stderr', "[Logger ERROR] __construct: {$msg}\n");
            throw new RuntimeException($msg);
        }
    }

    public function __destruct()
    {
        file_put_contents('php://stderr', "[Logger DEBUG] __destruct: entering\n");

        if (is_resource($this->appHandle)) {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: fclose(appHandle)\n");
            fclose($this->appHandle);
        } else {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: appHandle not a resource\n");
        }

        if ($this->debugHandle !== null && is_resource($this->debugHandle)) {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: fclose(debugHandle)\n");
            fclose($this->debugHandle);
        } else {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: debugHandle disabled or not a resource\n");
        }

        if (is_resource($this->errorHandle)) {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: fclose(errorHandle)\n");
            fclose($this->errorHandle);
        } else {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: errorHandle not a resource\n");
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_INFO, $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        if (! $this->debugEnabled) {
            // APP_DEBUG=false → skip entirely
            return;
        }
        $this->write(self::LEVEL_DEBUG, $message, $context);
    }

    public function warn(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_WARN, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_ERROR, $message, $context);
    }

    private function write(string $level, string $message, array $context = []): void
    {
        // 1) Build timestamp
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                 ->format('Y-m-d\TH:i:s.u\Z');
        file_put_contents(
            'php://stderr',
            "[Logger DEBUG] write: timestamp={$now}, level={$level}, message={$message}\n"
        );

        // 2) Build the log line
        $parts = [
            "timestamp={$now}",
            "level={$level}",
            "message=" . $this->escapeValue($message),
        ];
        file_put_contents(
            'php://stderr',
            "[Logger DEBUG] write: initial parts = " . implode(' | ', $parts) . "\n"
        );

        // 3) Append context
        foreach ($context as $key => $val) {
            file_put_contents(
                'php://stderr',
                "[Logger DEBUG] write: sanitizing key='{$key}', value='" . (string)$val . "'\n"
            );
            $sanitizedKey = $this->sanitizeKey($key);
            $escapedVal   = $this->escapeValue((string)$val);
            $parts[]      = "{$sanitizedKey}={$escapedVal}";
            file_put_contents(
                'php://stderr',
                "[Logger DEBUG] write: appended '{$sanitizedKey}={$escapedVal}'\n"
            );
        }

        // 4) Final line text
        $line = implode(' ', $parts) . PHP_EOL;
        file_put_contents('php://stderr', "[Logger DEBUG] write: final line = {$line}\n");

        // 5) Write to debug.log if enabled
        if ($this->debugEnabled && $this->debugHandle !== null) {
            if (flock($this->debugHandle, LOCK_EX)) {
                file_put_contents('php://stderr', "[Logger DEBUG] write: flock on debugHandle, writing\n");
                fwrite($this->debugHandle, $line);
                fflush($this->debugHandle);
                flock($this->debugHandle, LOCK_UN);
                file_put_contents('php://stderr', "[Logger DEBUG] write: released flock on debugHandle\n");
            } else {
                file_put_contents(
                    'php://stderr',
                    "[Logger WARN] write: could not acquire flock on debugHandle; dropping debug log\n"
                );
            }
        }

        // 6) Write to app.log when level ≠ DEBUG
        if ($level !== self::LEVEL_DEBUG) {
            if (flock($this->appHandle, LOCK_EX)) {
                file_put_contents('php://stderr', "[Logger DEBUG] write: flock on appHandle, writing\n");
                fwrite($this->appHandle, $line);
                fflush($this->appHandle);
                flock($this->appHandle, LOCK_UN);
                file_put_contents('php://stderr', "[Logger DEBUG] write: released flock on appHandle\n");
            } else {
                file_put_contents(
                    'php://stderr',
                    "[Logger WARN] write: could not acquire flock on appHandle; dropping app log\n"
                );
            }
        }

        // 7) Write ERROR‐only to error.log
        if ($level === self::LEVEL_ERROR) {
            if (flock($this->errorHandle, LOCK_EX)) {
                file_put_contents('php://stderr', "[Logger DEBUG] write: flock on errorHandle, writing\n");
                fwrite($this->errorHandle, $line);
                fflush($this->errorHandle);
                flock($this->errorHandle, LOCK_UN);
                file_put_contents('php://stderr', "[Logger DEBUG] write: released flock on errorHandle\n");
            } else {
                file_put_contents(
                    'php://stderr',
                    "[Logger WARN] write: could not acquire flock on errorHandle; dropping error log\n"
                );
            }
        }
    }

    private function sanitizeKey(string $key): string
    {
        file_put_contents('php://stderr', "[Logger DEBUG] sanitizeKey: checking '{$key}'\n");
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
            file_put_contents('php://stderr', "[Logger ERROR] sanitizeKey: invalid key '{$key}'\n");
            throw new InvalidArgumentException("Invalid context key for logger: {$key}");
        }
        return $key;
    }

    private function escapeValue(string $value): string
    {
        file_put_contents('php://stderr', "[Logger DEBUG] escapeValue: raw value = {$value}\n");
        $escaped = addcslashes($value, "\\\"");
        file_put_contents('php://stderr', "[Logger DEBUG] escapeValue: escaped value = {$escaped}\n");
        return '"' . $escaped . '"';
    }
}
