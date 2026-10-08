<?php

use App\Http\Middleware\VerifyFirebaseToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * Laravel 13 application bootstrap. Merge the highlighted sections into your
 * project's existing bootstrap/app.php if you scaffolded a fresh app.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API bearer-token guard (Firebase ID token).
        $middleware->alias([
            'firebase' => VerifyFirebaseToken::class,
        ]);

        // The WhatsApp webhook must be exempt from CSRF (Meta can't send a token).
        $middleware->validateCsrfTokens(except: [
            'webhook/whatsapp',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('reminders:dispatch')->everyMinute()->withoutOverlapping();
        // T9 will register the reminders command here, e.g.:
        // $schedule->command('reminders:dispatch')->everyMinute()->withoutOverlapping();
    })
    ->create();
