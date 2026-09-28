<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (TokenMismatchException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your session expired. Please refresh and try again.',
                ], 419);
            }

            $route = $request->is('admin/*') && $request->user()
                ? 'admin.dashboard'
                : 'login';

            return redirect()
                ->route($route)
                ->with('warning', 'Your session expired. Please refresh the page and try again.');
        });

        $this->renderable(function (ValidationException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The given data was invalid.',
                    'errors' => $exception->errors(),
                ], $exception->status);
            }
        });

        $this->renderable(function (PostTooLargeException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The uploaded payload exceeds the server request size limit.',
                    'errors' => [
                        'files' => ['The uploaded payload exceeds the server request size limit.'],
                    ],
                ], 413);
            }

            if ($request->is('admin/*')) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors([
                        'files' => 'The uploaded payload exceeds the server request size limit.',
                    ])
                    ->with('error', 'The uploaded payload exceeds the server request size limit.');
            }
        });

        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (Throwable $exception, Request $request) {
            if ($this->shouldPassThrough($exception) || config('app.debug')) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $reference = Str::upper(Str::random(10));

            Log::error('Unhandled application exception', [
                'reference' => $reference,
                'exception' => $exception,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $status >= 500
                        ? 'An unexpected server error occurred.'
                        : ($exception->getMessage() ?: 'The requested resource could not be processed.'),
                    'reference' => $reference,
                ], $status);
            }

            if ($status >= 500 && $request->is('admin/*') && ! $request->isMethod('GET')) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'We could not complete that action. Reference: ' . $reference);
            }

            if ($status === 404) {
                return response()->view('errors.404', [], 404);
            }

            if ($status === 419) {
                return response()->view('errors.419', [], 419);
            }

            if ($status >= 500) {
                return response()->view('errors.500', [
                    'reference' => $reference,
                ], $status);
            }

            return null;
        });
    }

    protected function shouldPassThrough(Throwable $exception): bool
    {
        return $exception instanceof ValidationException
            || $exception instanceof TokenMismatchException
            || $exception instanceof PostTooLargeException
            || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() < 500);
    }
}
