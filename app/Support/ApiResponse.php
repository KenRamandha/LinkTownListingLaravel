<?php

namespace App\Support;

trait ApiResponse
{
    protected function ok(mixed $data = null, string $message = 'OK', array $meta = [], int $status = 200)
    {
        return response()->json([
            'success' => true,
            'code'    => $status,
            'message' => $message,
            'data'    => $data,
            'meta'    => (object) $meta,
        ], $status);
    }

    protected function fail(string $message, int $status = 400, string $code = 'BAD_REQUEST', ?array $errors = null, array $meta = [])
    {
        $payload = [
            'success' => false,
            'code'    => $status,
            'message' => $message,
        ];
        if (!is_null($errors)) $payload['errors'] = $errors;
        if (!empty($meta))     $payload['meta']   = $meta;

        return response()->json($payload, $status);
    }
}
