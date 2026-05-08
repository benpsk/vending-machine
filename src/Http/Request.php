<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /** @var array<string, mixed> Mutable per-request scratchpad set by middleware (e.g. auth user). */
    public array $attributes = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     * @param array<string, mixed> $server
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $headers = [],
        public readonly array $cookies = [],
        public readonly array $server = [],
    ) {
    }

    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public static function fromGlobals(): self
    {
        $raw = file_get_contents('php://input');
        return self::fromArrays(
            server: $_SERVER,
            query: $_GET,
            post: $_POST,
            cookies: $_COOKIE,
            rawBody: is_string($raw) ? $raw : null,
        );
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $cookies
     */
    public static function fromArrays(
        array $server,
        array $query = [],
        array $post = [],
        array $cookies = [],
        ?string $rawBody = null,
    ): self {
        $method = strtoupper((string)($server['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string)($server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $headers = self::collectHeaders($server);

        $body = $post;
        $contentType = $headers['content-type'] ?? '';
        if (
            $method !== 'GET'
            && str_contains($contentType, 'application/json')
            && $rawBody !== null
            && $rawBody !== ''
        ) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return new self(
            method: $method,
            path: $path,
            query: $query,
            body: $body,
            headers: $headers,
            cookies: $cookies,
            server: $server,
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if ($auth === null) {
            return null;
        }
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m) === 1) {
            return $m[1];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function collectHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string)$value;
            }
        }
        if (isset($server['CONTENT_TYPE']) && is_scalar($server['CONTENT_TYPE'])) {
            $headers['content-type'] = (string)$server['CONTENT_TYPE'];
        }
        if (isset($server['CONTENT_LENGTH']) && is_scalar($server['CONTENT_LENGTH'])) {
            $headers['content-length'] = (string)$server['CONTENT_LENGTH'];
        }
        return $headers;
    }
}
