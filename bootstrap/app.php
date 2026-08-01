<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            SetLocale::class,
        ]);
        $middleware->alias([
            'set.locale' => SetLocale::class,
            'user.active' => EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // The mobile API must always answer in the documented JSON shape,
        // never an HTML error page or a web redirect.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => __('api.unauthenticated')], 401);
            }

            return null;
        });

        // A missing record and a record owned by someone else are both reported
        // as 404, so the API never confirms that another owner's parcel exists.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => __('api.not_found')], 404);
            }

            return null;
        });

        // Anything unhandled is logged in full but answered generically, so a
        // stack trace or SQL fragment never reaches the app — even with
        // APP_DEBUG on. Handling this here keeps controllers free of try/catch.
        $exceptions->render(function (Throwable $e, Request $request) {
            // Validation and auth failures carry their own documented shape and
            // status, so only genuinely unexpected errors are rewritten here.
            $handledElsewhere = $e instanceof HttpExceptionInterface
                || $e instanceof ValidationException
                || $e instanceof AuthenticationException;

            if (! $request->is('api/*') || $handledElsewhere) {
                return null;
            }

            Log::error('Unhandled API exception', [
                'path' => $request->path(),
                'exception' => $e,
            ]);

            return response()->json(['message' => __('api.server_error')], 500);
        });
    })->create();
