<?php

declare(strict_types=1);

namespace App\Auth\Storage;

final class ArraySessionStorage implements SessionStorageInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    private bool $started = false;

    public string $id = 'test-session-id';

    public function start(): void
    {
        $this->started = true;
    }

    public function regenerateId(): void
    {
        $this->id = bin2hex(random_bytes(16));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function isStarted(): bool
    {
        return $this->started;
    }
}
