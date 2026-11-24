<?php

namespace Tests\Feature;

use App\Enums\ListingStatus;
use App\Models\Alert;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Auth is stubbed: the first user created in each test is the current user.
 */
class AlertPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_index_lists_the_current_users_alerts_newest_first(): void
    {
        $older = Alert::factory()->for($this->user)->create(['created_at' => now()->subDay()]);
        $newer = Alert::factory()->for($this->user)->create();

        $this->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Alerts/Index')
                ->has('alerts.data', 2)
                ->where('alerts.data.0.id', $newer->id)
                ->where('alerts.data.1.id', $older->id)
                ->has('alerts.data.0', fn (AssertableInertia $alert) => $alert
                    ->hasAll('id', 'alerted_at', 'listing', 'saved_searches')
                )
            );
    }

    /**
     * Mirrors the listings index test: alerts created in the same second must
     * page in a stable order, or rows repeat or go missing between pages.
     */
    public function test_index_paginates_deterministically_when_alerted_at_ties(): void
    {
        $alerts = Alert::factory(16)->for($this->user)->create(['created_at' => now()->subHour()]);

        $page = fn (int $number) => $this->get("/alerts?page={$number}")->viewData('page')['props']['alerts'];

        $first = $page(1);
        $second = $page(2);
        $firstIds = collect($first['data'])->pluck('id')->all();
        $secondIds = collect($second['data'])->pluck('id')->all();

        // Ties are broken by highest id first, so the order is fully defined.
        $this->assertSame($alerts->pluck('id')->sortDesc()->take(15)->values()->all(), $firstIds);
        $this->assertCount(1, $secondIds);
        $this->assertSame([], array_intersect($firstIds, $secondIds), 'Pages must not overlap.');
        $this->assertCount(16, array_unique([...$firstIds, ...$secondIds]));
        $this->assertSame(2, $first['meta']['last_page']);
        $this->assertNotNull($first['links']['next']);
    }

    public function test_index_excludes_other_users_alerts(): void
    {
        Alert::factory()->for($this->user)->create();
        Alert::factory()->for(User::factory()->create())->create();

        $this->get('/alerts')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('alerts.data', 1));
    }

    /**
     * Only properties a buyer can still act on are shown. The alert record is
     * kept, so a relisted property shows up again (without a second alert).
     */
    public function test_index_hides_alerts_for_listings_that_are_no_longer_live(): void
    {
        $alert = Alert::factory()->for($this->user)->create();

        $alert->listing->update(['status' => ListingStatus::Sold]);
        $this->get('/alerts')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('alerts.data', 0));

        $alert->listing->update(['status' => ListingStatus::Live]);
        $this->get('/alerts')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 1)
                ->where('alerts.data.0.id', $alert->id)
            );
    }

    public function test_index_shows_which_searches_matched(): void
    {
        $alert = Alert::factory()->for($this->user)->create();
        $alert->savedSearches()->attach(SavedSearch::factory(2)->for($this->user)->create());

        $this->get('/alerts')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data.0.saved_searches', 2)
            );
    }

    /**
     * Each alert renders its listing, the listing's branch and its matched
     * searches. They must be eager loaded, or the page repeats the N+1 that
     * the listings index has.
     */
    public function test_index_does_not_lazy_load_relations(): void
    {
        // Alerts first, searches after: each alert's factory listing goes live
        // and runs the real matching listener, which would otherwise match an
        // earlier search and create the alert before the factory does.
        $alerts = Alert::factory(3)->for($this->user)->create();
        foreach ($alerts as $alert) {
            $alert->savedSearches()->attach(SavedSearch::factory()->for($this->user)->create());
        }

        Model::preventLazyLoading();

        try {
            $this->get('/alerts')->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_guests_are_redirected_to_the_listings_page(): void
    {
        $this->user->delete();

        $this->get('/alerts')->assertRedirect('/');
    }
}
