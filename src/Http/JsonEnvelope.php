<?php

declare(strict_types=1);

namespace App\Http;

final class JsonEnvelope
{
    /**
     * @param array<string, mixed>|null $meta
     */
    public static function success(mixed $data, ?array $meta = null, int $status = 200): Response
    {
        $body = ['data' => $data, 'error' => null];
        if ($meta !== null) {
            $body['meta'] = $meta;
        }
        return Response::json($body, $status);
    }

    /**
     * @param array<string, mixed>|null $extra Extra fields merged into the error object (e.g. 'fields' for validation).
     */
    public static function error(string $code, string $message, int $status, ?array $extra = null): Response
    {
        $error = ['code' => $code, 'message' => $message];
        if ($extra !== null) {
            $error = array_merge($error, $extra);
        }
        return Response::json(['data' => null, 'error' => $error], $status);
    }
}
