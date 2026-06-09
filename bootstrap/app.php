<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register the role middleware alias
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        // Stateful domains for Sanctum SPA (Nuxt frontend)
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Render all exceptions as JSON for the API
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Return clean 404 JSON instead of HTML for model-not-found
        $exceptions->render(function (
            \Illuminate\Database\Eloquent\ModelNotFoundException $e,
            Request $request
        ) {
            return response()->json([
                'message' => 'The requested resource was not found.',
            ], 404);
        });

        // Clean 403 for authorization failures
        $exceptions->render(function (
            \Illuminate\Auth\Access\AuthorizationException $e,
            Request $request
        ) {
            return response()->json([
                'message' => 'You are not authorized to perform this action.',
            ], 403);
        });

        // Clean 401 for unauthenticated access
        $exceptions->render(function (
            \Illuminate\Auth\AuthenticationException $e,
            Request $request
        ) {
            return response()->json([
                'message' => 'Unauthenticated. Please log in.',
            ], 401);
        });

        // Clean 422 for validation errors
        $exceptions->render(function (
            \Illuminate\Validation\ValidationException $e,
            Request $request
        ) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        });
    })
    ->create();
