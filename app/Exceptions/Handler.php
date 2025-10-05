<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected function wantsJson($request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    public function render($request, Throwable $e)
    {
        if (!$this->wantsJson($request)) {
            return parent::render($request, $e);
        }

        $debug = (bool) config('app.debug');

        // VALIDATION
        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'code'    => 'VALIDATION_ERROR',
                'errors'  => $e->errors(),
            ], 422);
        }

        // AUTH
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terautentikasi',
                'code'    => 'UNAUTHORIZED',
            ], 401);
        }

        if ($e instanceof AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin',
                'code'    => 'FORBIDDEN',
            ], 403);
        }

        // NOT FOUND
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Resource tidak ditemukan',
                'code'    => 'NOT_FOUND',
            ], 404);
        }

        // METHOD NOT ALLOWED
        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Metode tidak diizinkan untuk endpoint ini',
                'code'    => 'METHOD_NOT_ALLOWED',
            ], 405);
        }

        // RATE LIMIT
        if ($e instanceof ThrottleRequestsException) {
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak permintaan, coba lagi nanti',
                'code'    => 'TOO_MANY_REQUESTS',
            ], 429);
        }

        // QUERY / DB
        if ($e instanceof QueryException) {
            return response()->json([
                'success' => false,
                'message' => 'Kesalahan database',
                'code'    => 'DB_ERROR',
                'meta'    => $debug ? ['sql' => $e->getSql(), 'bindings' => $e->getBindings()] : (object)[],
            ], 500);
        }

        // HTTP EXCEPTION (custom status)
        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Terjadi kesalahan',
                'code'    => match ($status) {
                    400 => 'BAD_REQUEST',
                    401 => 'UNAUTHORIZED',
                    403 => 'FORBIDDEN',
                    404 => 'NOT_FOUND',
                    405 => 'METHOD_NOT_ALLOWED',
                    409 => 'CONFLICT',
                    422 => 'UNPROCESSABLE_ENTITY',
                    default => 'HTTP_ERROR',
                },
                'meta'    => $debug ? ['status' => $status] : (object)[],
            ], $status);
        }

        // DEFAULT 500
        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan pada server',
            'code'    => 'SERVER_ERROR',
            'meta'    => $debug ? ['exception' => get_class($e), 'message' => $e->getMessage()] : (object)[],
        ], 500);
    }
}
