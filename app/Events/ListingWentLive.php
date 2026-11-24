<?php

namespace App\Events;

use App\Models\Listing;
use Carbon\CarbonImmutable;
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

    /**
     * When the listing went live. Queued listeners run later, so they use this
     * rather than "now" to tell what existed at the moment it happened.
     * (listed_at isn't used: whoever makes a listing live sets it, so it can be
     * backdated or missing.)
     */
    public CarbonImmutable $wentLiveAt;

    public function __construct(public Listing $listing)
    {
        $this->wentLiveAt = CarbonImmutable::now();
    }
}
