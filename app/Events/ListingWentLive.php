<?php

namespace App\Events;

use App\Models\Listing;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A listing has just come onto the market: created as live, or moved to live
 * from another status. Dispatched after the surrounding transaction commits,
 * so listeners never see a listing that is later rolled back.
 */
class ListingWentLive implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Listing $listing) {}
}
