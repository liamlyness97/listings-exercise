<?php

namespace App\Observers;

use App\Enums\ListingStatus;
use App\Events\ListingWentLive;
use App\Models\Listing;

/**
 * Nothing in the app has a "publish" step yet; status is set wherever a listing
 * is saved. Observing the model catches every path that makes a listing live
 * (an admin screen, an import, tinker) so alerts can't be bypassed by accident.
 */
class ListingObserver
{
    public function created(Listing $listing): void
    {
        if ($listing->status === ListingStatus::Live) {
            ListingWentLive::dispatch($listing);
        }
    }

    /**
     * Only a change *to* live counts. Edits to a listing that is already live,
     * such as a price change, don't dispatch anything.
     */
    public function updated(Listing $listing): void
    {
        if ($listing->wasChanged('status') && $listing->status === ListingStatus::Live) {
            ListingWentLive::dispatch($listing);
        }
    }
}
