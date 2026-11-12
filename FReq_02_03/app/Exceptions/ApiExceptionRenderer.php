<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class ApiExceptionRenderer
{
    public static function render(Throwable $e): JsonResponse
    {
        if ($e instanceof HttpExceptionInterface && $e->getPrevious() instanceof ModelNotFoundException) {
            $e = $e->getPrevious();
        }

        if ($e instanceof HttpExceptionInterface && $e->getPrevious() instanceof AuthorizationException) {
            $e = $e->getPrevious();
        }

        if ($e instanceof ApiException) {
            return self::payload($e->getMessage(), $e->status(), $e->errors());
        }

        if ($e instanceof ValidationException) {
            return self::payload('Validation failed', 422, $e->errors());
        }

        if ($e instanceof TokenExpiredException) {
            return self::payload('Token has expired', 401);
        }

        if ($e instanceof JWTException || $e instanceof AuthenticationException) {
            return self::payload('Unauthenticated. A valid token is required.', 401);
        }

        if ($e instanceof AuthorizationException) {
            return self::payload('You are not allowed to perform this action.', 403);
        }

        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());

            return self::payload("{$model} not found.", 404);
        }

        if ($e instanceof NotFoundHttpException) {
            return self::payload('The requested endpoint does not exist.', 404);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return self::payload('Method not allowed for this endpoint.', 405, [], $e->getHeaders());
        }

        if ($e instanceof ThrottleRequestsException) {
            return self::payload('Too many requests. Please try again later.', 429, [], $e->getHeaders());
        }

        if ($e instanceof HttpExceptionInterface) {
            return self::payload($e->getMessage() ?: 'Request failed.', $e->getStatusCode(), [], $e->getHeaders());
        }

        $payload = ['status' => false, 'code' => 500, 'message' => 'Server error. Please try again later.', 'data' => null];

        if (config('app.debug')) {
            $payload['message'] = $e->getMessage() ?: $payload['message'];
            $payload['exception'] = get_class($e);
            $payload['file'] = $e->getFile();
            $payload['line'] = $e->getLine();
        }

        return response()->json($payload, 500);
    }

    private static function payload(string $message, int $status, array $errors = [], array $headers = []): JsonResponse
    {
        $body = ['status' => false, 'code' => $status, 'message' => $message, 'data' => null];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status, $headers);
    }
}
