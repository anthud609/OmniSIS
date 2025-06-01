<?php
declare(strict_types=1);

namespace OmniSIS\Core;

use InvalidArgumentException;
use RuntimeException;

/**
 * A PSR-3–compatible logger that writes to three files:
 *
 *  - storage/logs/app.log       : all levels EXCEPT DEBUG
 *  - storage/logs/debug.log     : all levels, including DEBUG (only if APP_DEBUG=true)
 *  - storage/logs/error.log     : only ERROR and above (ERROR, CRITICAL, ALERT, EMERGENCY)
 *
 * Whether DEBUG‐level (and everything else) is written to debug.log
 * is controlled by the APP_DEBUG environment variable (loaded via phpdotenv).
 */
final class Logger
{
    /** PSR-3 level constants **/
    private const LEVEL_EMERGENCY = 'EMERGENCY';
    private const LEVEL_ALERT     = 'ALERT';
    private const LEVEL_CRITICAL  = 'CRITICAL';
    private const LEVEL_ERROR     = 'ERROR';
    private const LEVEL_warn   = 'warn';
    private const LEVEL_NOTICE    = 'NOTICE';
    private const LEVEL_INFO      = 'INFO';
    private const LEVEL_DEBUG     = 'DEBUG';

    private const APP_LOG_FILE_RELATIVE   = '/../storage/logs/app.log';
    private const DEBUG_LOG_FILE_RELATIVE = '/../storage/logs/debug.log';
    private const ERROR_LOG_FILE_RELATIVE = '/../storage/logs/error.log';

    private string      $appFilePath;
    private ?string     $debugFilePath = null;
    private string      $errorFilePath;
    private $appHandle;
    private $debugHandle;   // null if debug disabled
    private $errorHandle;
    private bool        $debugEnabled;

    /** @var Logger|null */
    private static ?Logger $instance = null;

    /**
     * Return the singleton Logger.
     */
    public static function getInstance(): Logger
    {
        if (self::$instance === null) {
            // We can’t yet log into our own logger, so write to stderr:
            file_put_contents('php://stderr', "[Logger DEBUG] getInstance: creating new Logger()\n");
            self::$instance = new Logger();
        } else {
            file_put_contents('php://stderr', "[Logger DEBUG] getInstance: reusing existing instance\n");
        }
        return self::$instance;
    }

    /**
     * Private constructor: open log files, initialize flags.
     */
    private function __construct()
    {
        file_put_contents('php://stderr', "[Logger DEBUG] __construct: entering\n");

        //
        // ─── 1) READ APP_DEBUG FROM ENVIRONMENT ────────────────────────────────────
        //
        // phpdotenv in Application::__construct() has already loaded .env into getenv()/$_ENV.
        $rawEnv = getenv('APP_DEBUG');
        if ($rawEnv === false || trim($rawEnv) === '') {
            $rawEnv = $_ENV['APP_DEBUG'] ?? 'false';
        }
        $this->debugEnabled = filter_var($rawEnv, FILTER_VALIDATE_BOOLEAN);
        file_put_contents(
            'php://stderr',
            "[Logger DEBUG] __construct: debugEnabled = " . ($this->debugEnabled ? 'true' : 'false') . "\n"
        );

        //
        // ─── 2) COMPUTE ABSOLUTE PATHS ────────────────────────────────────────────
        //
        // __DIR__ is “project-root/app/Core”. We want “project-root”.
        $baseDir = dirname(__DIR__, 2); // project-root
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
            file_put_contents(
                'php://stderr',
                "[Logger DEBUG] __construct: debugFilePath disabled (APP_DEBUG=false)\n"
            );
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
            file_put_contents('php://stderr', "[Logger DEBUG] __construct: ensuring directory exists: {$dir}\n");
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

    /**
     * Close all file handles.
     */
    public function __destruct()
    {
        file_put_contents('php://stderr', "[Logger DEBUG] __destruct: entering\n");

        if (is_resource($this->appHandle)) {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: fclose(appHandle)\n");
            fclose($this->appHandle);
        } else {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: appHandle not resource\n");
        }

        if ($this->debugHandle !== null && is_resource($this->debugHandle)) {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: fclose(debugHandle)\n");
            fclose($this->debugHandle);
        } else {
            file_put_contents(
                'php://stderr',
                "[Logger DEBUG] __destruct: debugHandle disabled or not resource\n"
            );
        }

        if (is_resource($this->errorHandle)) {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: fclose(errorHandle)\n");
            fclose($this->errorHandle);
        } else {
            file_put_contents('php://stderr', "[Logger DEBUG] __destruct: errorHandle not resource\n");
        }
    }

    /* ─── PSR-3 LEVEL METHODS ─────────────────────────────────────────────────── */

    public function emergency(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_EMERGENCY, $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_ALERT, $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_CRITICAL, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_ERROR, $message, $context);
    }

    public function warn(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_warn, $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_NOTICE, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write(self::LEVEL_INFO, $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        if (! $this->debugEnabled) {
            // APP_DEBUG=false → skip debug (and skip writing to debug.log entirely)
            return;
        }
        $this->write(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Core write logic: routes each level to debug.log, app.log, error.log as needed.
     */
    private function write(string $level, string $message, array $context = []): void
    {
        // 1) Build timestamp in UTC
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                 ->format('Y-m-d\TH:i:s.u\Z');
        file_put_contents(
            'php://stderr',
            "[Logger DEBUG] write: timestamp={$now}, level={$level}, message={$message}\n"
        );

        // 2) Build "key=value" segments for the log line
        $parts = [
            "timestamp={$now}",
            "level={$level}",
            "message=" . $this->escapeValue($message),
        ];
        file_put_contents(
            'php://stderr',
            "[Logger DEBUG] write: initial parts = " . implode(' | ', $parts) . "\n"
        );

        // 3) Append context key/value pairs
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

        // 4) Final line (one‐line string)
        $line = implode(' ', $parts) . PHP_EOL;
        file_put_contents('php://stderr', "[Logger DEBUG] write: final line = {$line}\n");

        // 5) Write to debug.log if APP_DEBUG=true
        if ($this->debugEnabled && $this->debugHandle !== null) {
            if (flock($this->debugHandle, LOCK_EX)) {
                file_put_contents(
                    'php://stderr',
                    "[Logger DEBUG] write: flock acquired on debugHandle, writing\n"
                );
                fwrite($this->debugHandle, $line);
                fflush($this->debugHandle);
                flock($this->debugHandle, LOCK_UN);
                file_put_contents(
                    'php://stderr',
                    "[Logger DEBUG] write: released flock on debugHandle\n"
                );
            } else {
                file_put_contents(
                    'php://stderr',
                    "[Logger WARN] write: could not acquire flock on debugHandle; dropping debug log\n"
                );
            }
        }

        // 6) Write to app.log for any level ≠ DEBUG
        if ($level !== self::LEVEL_DEBUG) {
            if (flock($this->appHandle, LOCK_EX)) {
                file_put_contents(
                    'php://stderr',
                    "[Logger DEBUG] write: flock acquired on appHandle, writing\n"
                );
                fwrite($this->appHandle, $line);
                fflush($this->appHandle);
                flock($this->appHandle, LOCK_UN);
                file_put_contents(
                    'php://stderr',
                    "[Logger DEBUG] write: released flock on appHandle\n"
                );
            } else {
                file_put_contents(
                    'php://stderr',
                    "[Logger WARN] write: could not acquire flock on appHandle; dropping app log\n"
                );
            }
        }

        // 7) Write to error.log if level is ERROR or above (ERROR, CRITICAL, ALERT, EMERGENCY)
        if (in_array($level, [
            self::LEVEL_ERROR,
            self::LEVEL_CRITICAL,
            self::LEVEL_ALERT,
            self::LEVEL_EMERGENCY
        ], true)) {
            if (flock($this->errorHandle, LOCK_EX)) {
                file_put_contents(
                    'php://stderr',
                    "[Logger DEBUG] write: flock acquired on errorHandle, writing\n"
                );
                fwrite($this->errorHandle, $line);
                fflush($this->errorHandle);
                flock($this->errorHandle, LOCK_UN);
                file_put_contents(
                    'php://stderr',
                    "[Logger DEBUG] write: released flock on errorHandle\n"
                );
            } else {
                file_put_contents(
                    'php://stderr',
                    "[Logger WARN] write: could not acquire flock on errorHandle; dropping error log\n"
                );
            }
        }
    }

    /**
     * Ensure context key is alphanumeric or underscore.
     *
     * @throws InvalidArgumentException
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
     * Escape a value by backslash-escaping quotes/backslashes and wrapping in quotes.
     */
    private function escapeValue(string $value): string
    {
        file_put_contents('php://stderr', "[Logger DEBUG] escapeValue: raw value = {$value}\n");
        $escaped = addcslashes($value, "\\\"");
        file_put_contents('php://stderr', "[Logger DEBUG] escapeValue: escaped value = {$escaped}\n");
        return '"' . $escaped . '"';
    }
}


$logger = \OmniSIS\Core\Logger::getInstance();
$logger->emergency('*** TEST: EMERGENCY level ***');
$logger->alert    ('*** TEST: ALERT level ***');
$logger->critical ('*** TEST: CRITICAL level ***');
$logger->error    ('*** TEST: ERROR level ***');
$logger->warn  ('*** TEST: warn level ***');
$logger->notice   ('*** TEST: NOTICE level ***');
$logger->info     ('*** TEST: INFO level ***');
$logger->debug    ('*** TEST: DEBUG level ***');

/* Expected outcome (when APP_DEBUG=true):
   - storage/logs/debug.log should contain all eight lines in order.
   - storage/logs/app.log   should contain the seven lines EXCEPT “DEBUG”:
       EMERGENCY, ALERT, CRITICAL, ERROR, warn, NOTICE, INFO
   - storage/logs/error.log should contain only:
       EMERGENCY, ALERT, CRITICAL, ERROR
   (DEBUG and lower‐severity levels do not go to error.log.)
*/

