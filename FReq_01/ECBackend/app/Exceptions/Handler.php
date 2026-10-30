<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    protected $dontReport = [
        ApiException::class,
    ];

    public function render($request, Throwable $e): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->renderApiException($e);
        }

        return parent::render($request, $e);
    }

    protected function renderApiException(Throwable $e): JsonResponse
    {
        if ($e instanceof HttpExceptionInterface && $e->getPrevious() instanceof ModelNotFoundException) {
            $e = $e->getPrevious();
        }

        if ($e instanceof HttpExceptionInterface && $e->getPrevious() instanceof AuthorizationException) {
            $e = $e->getPrevious();
        }

        if ($e instanceof ApiException) {
            return $this->payload($e->getMessage(), $e->status(), $e->errors());
        }

        if ($e instanceof ValidationException) {
            return $this->payload('Validation failed', 422, $e->errors());
        }

        if ($e instanceof TokenExpiredException) {
            return $this->payload('Token has expired', 401);
        }

        if ($e instanceof JWTException || $e instanceof AuthenticationException) {
            return $this->payload('Unauthenticated. A valid token is required.', 401);
        }

        if ($e instanceof AuthorizationException) {
            return $this->payload('You are not allowed to perform this action.', 403);
        }

        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());

            return $this->payload("{$model} not found.", 404);
        }

        if ($e instanceof NotFoundHttpException) {
            return $this->payload('The requested endpoint does not exist.', 404);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return $this->payload('Method not allowed for this endpoint.', 405, [], $e->getHeaders());
        }

        if ($e instanceof ThrottleRequestsException) {
            return $this->payload('Too many requests. Please try again later.', 429, [], $e->getHeaders());
        }

        if ($e instanceof HttpExceptionInterface) {
            return $this->payload($e->getMessage() ?: 'Request failed.', $e->getStatusCode(), [], $e->getHeaders());
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

    protected function payload(string $message, int $status, array $errors = [], array $headers = []): JsonResponse
    {
        $body = ['status' => false, 'code' => $status, 'message' => $message, 'data' => null];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status, $headers);
    }
}
