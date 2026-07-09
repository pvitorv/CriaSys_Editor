<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class SafeJson
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    public static function response(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse(self::prepare($data), $status, $headers, self::JSON_FLAGS);
    }

    public static function message(string $message, int $status = 422): JsonResponse
    {
        return self::response([
            'message' => Utf8::clean($message) ?: 'Erro interno.',
        ], $status);
    }

    public static function prepare(mixed $data): mixed
    {
        if (is_string($data)) {
            return Utf8::clean($data);
        }

        if (is_array($data)) {
            return Utf8::cleanArray($data);
        }

        if (is_object($data) && method_exists($data, 'toArray')) {
            return Utf8::cleanArray($data->toArray());
        }

        return $data;
    }
}
