<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Events\ListingWentLive;
use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class ListingWentLiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([ListingWentLive::class]);
    }

    public function test_creating_a_live_listing_dispatches_the_event(): void
    {
        $listing = Listing::factory()->live()->create();

        Event::assertDispatched(ListingWentLive::class, fn (ListingWentLive $event) => $event->listing->is($listing));
    }

    public function test_creating_a_draft_listing_does_not_dispatch_the_event(): void
    {
        Listing::factory()->draft()->create();

        Event::assertNotDispatched(ListingWentLive::class);
    }

    public function test_publishing_a_draft_dispatches_the_event(): void
    {
        $listing = Listing::factory()->draft()->create();

        $listing->update(['status' => ListingStatus::Live, 'listed_at' => now()]);

        Event::assertDispatchedTimes(ListingWentLive::class, 1);
    }

    /**
     * Only going live counts. Editing a listing that is already live (a price
     * change, say) must not alert anyone again.
     */
    public function test_updating_a_live_listing_without_a_status_change_does_not_dispatch_the_event(): void
    {
        $listing = Listing::factory()->live()->create();
        Event::fake([ListingWentLive::class]);

        $listing->update(['price' => $listing->price - 10_000]);

        Event::assertNotDispatched(ListingWentLive::class);
    }

    public function test_moving_a_listing_off_live_does_not_dispatch_the_event(): void
    {
        $listing = Listing::factory()->live()->create();
        Event::fake([ListingWentLive::class]);

        $listing->update(['status' => ListingStatus::Sold]);

        Event::assertNotDispatched(ListingWentLive::class);
    }

    /**
     * The event waits for the transaction to commit, so a listing that is
     * rolled back never alerts anyone about a property that doesn't exist.
     */
    public function test_a_listing_rolled_back_in_a_transaction_does_not_dispatch_the_event(): void
    {
        try {
            DB::transaction(function () {
                Listing::factory()->live()->create();

                throw new RuntimeException('Something later in the save failed.');
            });
        } catch (RuntimeException) {
            // Expected: the transaction rolled back.
        }

        $this->assertSame(0, Listing::count());
        Event::assertNotDispatched(ListingWentLive::class);
    }

    /**
     * The seeder creates around 200 live listings; none of them are "new" to
     * anyone, so seeding must not trigger matching.
     */
    public function test_seeding_does_not_dispatch_the_event(): void
    {
        $this->seed();

        Event::assertNotDispatched(ListingWentLive::class);
    }
}
