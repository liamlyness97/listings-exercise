<?php

namespace App\Listeners;

use App\Enums\ListingStatus;
use App\Events\ListingWentLive;
use App\Models\Alert;
use App\Models\SavedSearch;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Turns a newly live listing into alerts: one per user whose saved searches it
 * matched at the moment it went live, linked to every search that matched.
 * Queued, so a listing going live never waits on matching.
 *
 * Safe to run more than once. The unique (user_id, listing_id) index and
 * createOrFirst() mean a retried job, or a listing that is relisted, finds the
 * existing alert rather than creating a second one.
 */
class CreateAlertsForMatchingSavedSearches implements ShouldQueue
{
    /**
     * A listing deleted before the job runs can't be matched; drop the job
     * rather than fail and retry it.
     */
    public bool $deleteWhenMissingModels = true;

    public function handle(ListingWentLive $event): void
    {
        $listing = $event->listing;

        // The job sees the listing as it is now, which may be later than when
        // it went live. If it has come off the market since, don't alert:
        // that would use up each user's one alert for it, and a genuine relist
        // later would then never reach them.
        if ($listing->status !== ListingStatus::Live) {
            return;
        }

        $matchesByUser = SavedSearch::query()
            ->matching($listing)
            // No backfill: only searches that existed when the listing went
            // live. The job may run much later, and searches saved in the
            // meantime must not hear about a listing that is already live.
            ->where('created_at', '<=', $event->wentLiveAt)
            ->get(['id', 'user_id'])
            ->groupBy('user_id');

        foreach ($matchesByUser as $userId => $searches) {
            $alert = Alert::query()->createOrFirst([
                'user_id' => $userId,
                'listing_id' => $listing->id,
            ]);

            $alert->savedSearches()->syncWithoutDetaching($searches->modelKeys());
        }
    }
}
