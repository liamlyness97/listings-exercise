<?php

use App\Http\Middleware\ActAsDemoUser;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // NOTE (take-home): auth is stubbed. Every request is resolved as the
            // seeded demo user so you can build user-scoped features (e.g. saved
            // searches) against auth()->user() without wiring up a real login flow.
            // See the README ("Authentication") for the rationale.
            //
            // This must run before HandleInertiaRequests, which reads the user
            // when it builds the shared props.
            ActAsDemoUser::class,

            HandleInertiaRequests::class,
        ]);

        // Laravel sorts the `auth` middleware ahead of ordinary web middleware,
        // so without this it would run before the stub and see a guest.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: ActAsDemoUser::class,
        );

        // The framework's default sends guests to route('login'), which doesn't
        // exist while auth is stubbed. Until the database is seeded the stub
        // resolves no user, so user-scoped pages send guests to the listings.
        $middleware->redirectGuestsTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
