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

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($this->wantsJson($request)) {
            return response()->json([
                'success' => false,
                'code'    => 401,
                'message' => 'Anda belum terautentikasi',
            ], 401);
        }
        return parent::unauthenticated($request, $exception);
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
                'code'    => 422,
                'message' => 'Validasi gagal',
                'errors'  => $e->errors(),
            ], 422);
        }

        // AUTH
        if ($e instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'code'    => 401,
                'message' => 'Anda belum terautentikasi',
            ], 401);
        }

        if ($e instanceof AuthorizationException) {
            return response()->json([
                'success' => false,
                'code'    => 403,
                'message' => 'Anda tidak memiliki izin',
            ], 403);
        }

        // NOT FOUND
        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'code'    => 404,
                'message' => 'Resource tidak ditemukan',
            ], 404);
        }

        // METHOD NOT ALLOWED
        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'code'    => 405,
                'message' => 'Metode tidak diizinkan untuk endpoint ini',
            ], 405);
        }

        // RATE LIMIT
        if ($e instanceof ThrottleRequestsException) {
            return response()->json([
                'success' => false,
                'code'    => 429,
                'message' => 'Terlalu banyak permintaan, coba lagi nanti',
            ], 429);
        }

        // QUERY / DB
        if ($e instanceof QueryException) {
            return response()->json([
                'success' => false,
                'code'    => 500,
                'message' => 'Kesalahan database',
                'meta'    => $debug ? ['sql' => $e->getSql(), 'bindings' => $e->getBindings()] : (object)[],
            ], 500);
        }

        // HTTP EXCEPTION (custom status)
        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            return response()->json([
                'success' => false,
                'code'    => $status,
                'message' => $e->getMessage() ?: 'Terjadi kesalahan',
                'meta'    => $debug ? ['status' => $status] : (object)[],
            ], $status);
        }

        // DEFAULT 500
        return response()->json([
            'success' => false,
            'code'    => 500,
            'message' => 'Terjadi kesalahan pada server',
            'meta'    => $debug ? ['exception' => get_class($e), 'message' => $e->getMessage()] : (object)[],
        ], 500);
    }
}
