<?php

namespace App\Observers;

use App\Models\Rental\One\RentalOneAccount;

class Vehicle122AccountObserver
{
    public $afterCommit = true;

    /**
     * Handle the Vehicle122Account "created" event.
     */
    public function created(RentalOneAccount $rentalOneAccount): void {}

    /**
     * Handle the Vehicle122Account "updated" event.
     */
    public function updated(RentalOneAccount $rentalOneAccount): void
    {
        if ($rentalOneAccount->wasChanged('cookie_string')) {
            $rentalOneAccount->deleteCookies();
        }
    }

    /**
     * Handle the Vehicle122Account "deleted" event.
     */
    public function deleted(RentalOneAccount $rentalOneAccount): void
    {
        $rentalOneAccount->deleteCookies();
    }

    /**
     * Handle the Vehicle122Account "restored" event.
     */
    public function restored(RentalOneAccount $rentalOneAccount): void {}

    /**
     * Handle the Vehicle122Account "force deleted" event.
     */
    public function forceDeleted(RentalOneAccount $rentalOneAccount): void
    {
        $rentalOneAccount->deleteCookies();
    }
}
