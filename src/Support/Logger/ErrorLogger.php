<?php

declare(strict_types=1);

namespace App\Support\Logger;

final class ErrorLogger implements LoggerInterface
{
    public function info(string $message, array $context = []): void
    {
        $this->emit('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->emit('WARN', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->emit('ERROR', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function emit(string $level, string $message, array $context): void
    {
        $line = "[{$level}] {$message}";
        foreach ($context as $key => $value) {
            $line .= ' ' . $key . '=' . self::stringify($value);
        }
        error_log($line);
    }

    private static function stringify(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            $str = (string)$value;
            return str_contains($str, ' ') ? '"' . str_replace('"', '\\"', $str) . '"' : $str;
        }
        try {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?: '"<encode-failed>"';
        } catch (\Throwable) {
            return '"<unprintable>"';
        }
    }
}
