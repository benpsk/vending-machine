<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status = 200,
        public readonly array $headers = [],
        public readonly string $body = '',
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self(
            status: $status,
            headers: ['content-type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    /**
     * @param mixed $data
     */
    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        return new self(
            status: $status,
            headers: ['content-type' => 'application/json; charset=utf-8'],
            body: $body,
        );
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self(
            status: $status,
            headers: ['location' => $location],
            body: '',
        );
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->body;
    }
}
