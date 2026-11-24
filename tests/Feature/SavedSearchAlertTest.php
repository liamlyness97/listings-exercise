<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Events\ListingWentLive;
use App\Listeners\CreateAlertsForMatchingSavedSearches;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * End to end: a listing going live, through the observer, event and listener,
 * to alerts. The queue runs synchronously in tests.
 */
class SavedSearchAlertTest extends TestCase
{
    use RefreshDatabase;

    private Branch $manchester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manchester = Branch::factory()->create(['region' => 'Manchester']);
    }

    public function test_a_listing_going_live_alerts_a_user_with_a_matching_search(): void
    {
        $search = $this->searchFor(User::factory()->create(), ['region' => 'Manchester']);

        $listing = $this->goLive();

        $alert = Alert::sole();
        $this->assertSame($search->user_id, $alert->user_id);
        $this->assertTrue($alert->listing->is($listing));
        $this->assertEquals([$search->id], $alert->savedSearches->modelKeys());
    }

    public function test_a_non_matching_search_gets_no_alert(): void
    {
        $this->searchFor(User::factory()->create(), ['region' => 'Leeds']);

        $this->goLive();

        $this->assertSame(0, Alert::count());
    }

    /**
     * The alert is about the property, so a listing matching two of a user's
     * searches is one alert that remembers both.
     */
    public function test_two_matching_searches_produce_one_alert_linked_to_both(): void
    {
        $user = User::factory()->create();
        $byRegion = $this->searchFor($user, ['region' => 'Manchester']);
        $byType = $this->searchFor($user, ['property_type' => PropertyType::Flat]);

        $this->goLive();

        $alert = Alert::sole();
        $this->assertEqualsCanonicalizing([$byRegion->id, $byType->id], $alert->savedSearches->modelKeys());
    }

    public function test_each_matching_user_gets_their_own_alert(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->searchFor($first, ['region' => 'Manchester']);
        $this->searchFor($second, ['region' => 'Manchester']);

        $this->goLive();

        $this->assertEqualsCanonicalizing([$first->id, $second->id], Alert::pluck('user_id')->all());
    }

    public function test_a_listing_going_live_again_does_not_alert_twice(): void
    {
        $this->searchFor(User::factory()->create(), ['region' => 'Manchester']);
        $listing = $this->goLive();

        $listing->update(['status' => ListingStatus::UnderOffer]);
        $listing->update(['status' => ListingStatus::Live]);

        $this->assertSame(1, Alert::count());
    }

    /**
     * No backfill: a search only hears about listings that go live after it
     * was saved.
     */
    public function test_saving_a_search_does_not_alert_on_listings_already_live(): void
    {
        $this->goLive();

        $this->searchFor(User::factory()->create(), ['region' => 'Manchester']);

        $this->assertSame(0, Alert::count());
    }

    /**
     * The listener is queued, so a search can be saved after the listing went
     * live but before the job runs. That search must not hear about the
     * listing, or a worker outage would turn into a backfill burst.
     */
    public function test_a_search_saved_while_the_job_waits_is_not_alerted(): void
    {
        $listing = $this->goLive();
        $event = new ListingWentLive($listing);

        $this->travel(1)->minutes();
        $this->searchFor(User::factory()->create(), ['region' => 'Manchester']);
        app(CreateAlertsForMatchingSavedSearches::class)->handle($event);

        $this->assertSame(0, Alert::count());
    }

    /**
     * Queued jobs can run more than once after a failure; a retry must not
     * duplicate the alert or its links.
     */
    public function test_handling_the_same_event_twice_is_harmless(): void
    {
        $this->searchFor(User::factory()->create(), ['region' => 'Manchester']);
        $listing = $this->goLive();
        $listener = app(CreateAlertsForMatchingSavedSearches::class);

        $listener->handle(new ListingWentLive($listing));
        $listener->handle(new ListingWentLive($listing));

        $this->assertSame(1, Alert::count());
        $this->assertCount(1, Alert::sole()->savedSearches);
    }

    /**
     * The job sees the listing as it is when it runs. If it came off the
     * market in the meantime, alerting now would also use up the user's one
     * alert for it, so a genuine relist later would never reach them.
     */
    public function test_a_listing_taken_off_the_market_before_the_job_runs_is_not_alerted(): void
    {
        $this->searchFor(User::factory()->create(), ['region' => 'Manchester']);
        $listing = Listing::withoutEvents(fn () => $this->goLive());
        $event = new ListingWentLive($listing);

        Listing::withoutEvents(fn () => $listing->update(['status' => ListingStatus::Draft]));
        app(CreateAlertsForMatchingSavedSearches::class)->handle($event);

        $this->assertSame(0, Alert::count());
    }

    /**
     * A listing deleted before the job runs can't be restored by the queue;
     * the job should be dropped rather than fail and retry.
     */
    public function test_the_queued_job_is_dropped_if_the_listing_was_deleted(): void
    {
        Queue::fake();

        $this->goLive();

        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job) => $job->class === CreateAlertsForMatchingSavedSearches::class
                && $job->deleteWhenMissingModels === true,
        );
    }

    public function test_the_listener_is_queued(): void
    {
        Queue::fake();

        $this->goLive();

        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job) => $job->class === CreateAlertsForMatchingSavedSearches::class,
        );
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    private function searchFor(User $user, array $criteria): SavedSearch
    {
        return SavedSearch::factory()->for($user)->create(['max_price' => null, ...$criteria]);
    }

    private function goLive(): Listing
    {
        return Listing::factory()->live()->for($this->manchester)->create([
            'property_type' => PropertyType::Flat,
            'price' => 250_000,
            'bedrooms' => 2,
        ]);
    }
}
