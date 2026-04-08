<?php

namespace App\Support;

use Psr\Log\LoggerInterface;
use Stringable;

/**
 * PrefixedLogger - 클래스명과 메서드명을 자동으로 로그에 추가하는 Logger 래퍼
 */
class PrefixedLogger implements LoggerInterface
{
    private LoggerInterface $logger;
    private string $className;

    public function __construct(LoggerInterface $logger, string $className)
    {
        $this->logger = $logger;
        $this->className = class_basename($className);
    }

    /**
     * 메시지 앞에 클래스명과 메서드명 prefix 추가
     */
    private function addPrefix(string|Stringable $message): string
    {
        // debug_backtrace를 사용하여 호출한 메서드명 추적
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        // trace[0] = addPrefix
        // trace[1] = error/info/warning 등 (PrefixedLogger 메서드)
        // trace[2] = 실제 로그를 호출한 곳

        $method = $trace[2]['function'] ?? 'unknown';

        return "[{$this->className}::{$method}] {$message}";
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->logger->emergency($this->addPrefix($message), $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->logger->alert($this->addPrefix($message), $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->logger->critical($this->addPrefix($message), $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->logger->error($this->addPrefix($message), $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->logger->warning($this->addPrefix($message), $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->logger->notice($this->addPrefix($message), $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->logger->info($this->addPrefix($message), $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->logger->debug($this->addPrefix($message), $context);
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->logger->log($level, $this->addPrefix($message), $context);
    }
}
