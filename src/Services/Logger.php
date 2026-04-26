<?php
declare(strict_types=1);

namespace App\Services;

class Logger
{
    private const LOG_DIR = ROOT . '/logs';
    private string $logFile;

    private function __construct(string $channel = 'app')
    {
        if (!is_dir(self::LOG_DIR)) {
            mkdir(self::LOG_DIR, 0755, true);
        }
        $this->logFile = self::LOG_DIR . '/' . $channel . '.log';
    }

    public static function app(): self
    {
        return new self('app');
    }

    public static function audit(): self
    {
        return new self('audit');
    }

    public static function errors(): self
    {
        return new self('errors');
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        if (APP_DEBUG) {
            $this->log('DEBUG', $message, $context);
        }
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        $line = "[{$timestamp}] {$level}: {$message}{$contextStr}\n";

        error_log($line, 3, $this->logFile);
    }
}
