<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Reservation::observe(\App\Observers\ReservationObserver::class);
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\ConversationHandedOff::class,
            \App\Listeners\NotifyStaffOfHandoff::class
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\ReservationRequested::class,
            \App\Listeners\NotifyStaffOfRequest::class
        );

        // Push the same two events to the Flutter staff app (T14 Phase 4).
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\ConversationHandedOff::class,
            \App\Listeners\PushStaffOfHandoff::class
        );
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\ReservationRequested::class,
            \App\Listeners\PushStaffOfRequest::class
        );
    }
}
