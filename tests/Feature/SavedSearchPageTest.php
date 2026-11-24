<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\Alert;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Auth is stubbed: every request runs as the first user (see ActAsDemoUser),
 * so the user created first in each test is "the current user".
 */
class SavedSearchPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_index_lists_only_the_current_users_searches(): void
    {
        SavedSearch::factory()->for($this->user)->create();
        SavedSearch::factory()->for(User::factory()->create())->create();

        $this->get('/saved-searches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SavedSearches/Index')
                ->has('savedSearches', 1, fn (AssertableInertia $search) => $search
                    ->hasAll('id', 'property_type', 'property_type_label', 'region', 'max_price', 'min_bedrooms', 'created_at')
                )
            );
    }

    public function test_index_lists_searches_newest_first(): void
    {
        $older = SavedSearch::factory()->for($this->user)->create(['created_at' => now()->subDay()]);
        $newer = SavedSearch::factory()->for($this->user)->create();

        $this->get('/saved-searches')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('savedSearches.0.id', $newer->id)
                ->where('savedSearches.1.id', $older->id)
            );
    }

    public function test_store_saves_the_criteria_and_redirects(): void
    {
        $this->post('/saved-searches', ['region' => 'Manchester', 'max_price' => 300000])
            ->assertRedirect('/saved-searches');

        $this->assertDatabaseHas('saved_searches', [
            'user_id' => $this->user->id,
            'region' => 'Manchester',
            'max_price' => 300000,
            'property_type' => null,
            'min_bedrooms' => null,
        ]);
    }

    /**
     * An empty search would alert on every new listing.
     */
    public function test_store_requires_at_least_one_criterion(): void
    {
        $this->post('/saved-searches', ['region' => '', 'max_price' => ''])
            ->assertSessionHasErrors('criteria');

        $this->assertSame(0, SavedSearch::count());
    }

    /**
     * "0+ beds" matches every listing, so on its own it would be an
     * alert-on-everything search. It means "any", so it isn't a criterion.
     */
    public function test_zero_minimum_bedrooms_alone_does_not_count_as_a_criterion(): void
    {
        $this->post('/saved-searches', ['min_bedrooms' => 0])
            ->assertSessionHasErrors('criteria');

        $this->assertSame(0, SavedSearch::count());
    }

    public function test_zero_minimum_bedrooms_is_stored_as_any(): void
    {
        $this->post('/saved-searches', ['region' => 'Leeds', 'min_bedrooms' => 0]);

        $this->assertNull(SavedSearch::sole()->min_bedrooms);
    }

    public function test_store_rejects_an_invalid_property_type(): void
    {
        $this->post('/saved-searches', ['property_type' => 'castle'])
            ->assertSessionHasErrors('property_type');

        $this->assertSame(0, SavedSearch::count());
    }

    /**
     * max_price is an unsigned integer column. A larger budget must come back
     * as a field error, not a database error (SQLite doesn't enforce column
     * sizes, so only the validation rule protects MySQL here).
     */
    public function test_store_rejects_a_budget_larger_than_the_column_can_hold(): void
    {
        $this->post('/saved-searches', ['max_price' => 4_294_967_296])
            ->assertSessionHasErrors('max_price');

        $this->assertSame(0, SavedSearch::count());
    }

    public function test_store_accepts_the_largest_budget_the_column_can_hold(): void
    {
        $this->post('/saved-searches', ['max_price' => 4_294_967_295])
            ->assertSessionHasNoErrors();

        $this->assertSame(4_294_967_295, SavedSearch::sole()->max_price);
    }

    public function test_store_does_not_duplicate_an_identical_search(): void
    {
        $this->post('/saved-searches', ['property_type' => 'flat', 'region' => 'Leeds']);
        $this->post('/saved-searches', ['property_type' => 'flat', 'region' => 'Leeds']);

        $this->assertSame(1, SavedSearch::count());
    }

    /**
     * Guards against looking up an existing search by only the criteria in the
     * request: "Manchester" would then find "Manchester, 3+ beds" and be
     * treated as a duplicate of it.
     */
    public function test_store_treats_a_broader_search_as_a_new_search(): void
    {
        $this->post('/saved-searches', ['region' => 'Manchester', 'min_bedrooms' => 3]);
        $this->post('/saved-searches', ['region' => 'Manchester']);

        $this->assertSame(2, SavedSearch::count());
    }

    public function test_store_treats_a_narrower_search_as_a_new_search(): void
    {
        $this->post('/saved-searches', ['region' => 'Manchester']);
        $this->post('/saved-searches', ['region' => 'Manchester', 'min_bedrooms' => 3]);

        $this->assertSame(2, SavedSearch::count());
    }

    public function test_store_does_not_create_alerts_for_listings_already_live(): void
    {
        Listing::factory()->live()
            ->for(Branch::factory()->create(['region' => 'Manchester']))
            ->create(['property_type' => PropertyType::Flat]);

        $this->post('/saved-searches', ['region' => 'Manchester']);

        $this->assertSame(1, SavedSearch::count());
        $this->assertSame(0, Alert::count());
    }

    public function test_destroy_deletes_the_users_own_search(): void
    {
        $search = SavedSearch::factory()->for($this->user)->create();

        $this->delete("/saved-searches/{$search->id}")
            ->assertRedirect('/saved-searches');

        $this->assertModelMissing($search);
    }

    public function test_destroy_forbids_deleting_another_users_search(): void
    {
        $search = SavedSearch::factory()->for(User::factory()->create())->create();

        $this->delete("/saved-searches/{$search->id}")->assertForbidden();

        $this->assertModelExists($search);
    }

    /**
     * With no seeded user the stub resolves nobody, like a logged-out visitor.
     * There's no login page, so guests go back to the listings rather than
     * hitting an error.
     */
    public function test_guests_are_redirected_to_the_listings_page(): void
    {
        $this->user->delete();

        $this->get('/saved-searches')->assertRedirect('/');
        $this->post('/saved-searches', ['region' => 'Manchester'])->assertRedirect('/');

        $this->assertSame(0, SavedSearch::count());
    }
}
