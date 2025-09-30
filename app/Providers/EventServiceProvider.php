<?php

namespace App\Providers;

use App\Models\Configuration;
use App\Models\Rental\One\RentalOneAccount;
use App\Models\Rental\Payment\RentalPayment;
use App\Observers\ConfigObserver;
use App\Observers\RentalPaymentObserver;
use App\Observers\Vehicle122AccountObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    protected $observers = [
        Configuration::class    => [ConfigObserver::class],
        RentalOneAccount::class => [Vehicle122AccountObserver::class],
        RentalPayment::class    => [RentalPaymentObserver::class],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void {}

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
