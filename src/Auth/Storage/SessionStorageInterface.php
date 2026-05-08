<?php

declare(strict_types=1);

namespace App\Auth\Storage;

interface SessionStorageInterface
{
    public function start(): void;

    public function regenerateId(): void;

    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function has(string $key): bool;

    public function forget(string $key): void;

    public function clear(): void;
}
