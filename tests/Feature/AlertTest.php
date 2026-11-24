<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\SavedSearch;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One alert per user per listing is a database guarantee, not just
     * something the code happens to respect.
     */
    public function test_a_user_cannot_have_two_alerts_for_the_same_listing(): void
    {
        $alert = Alert::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Alert::factory()->create([
            'user_id' => $alert->user_id,
            'listing_id' => $alert->listing_id,
        ]);
    }

    /**
     * The listing was a genuine match when it went live, so removing the search
     * afterwards only removes the link, not the alert.
     */
    public function test_deleting_a_saved_search_keeps_its_alerts(): void
    {
        $alert = Alert::factory()->create();
        $search = SavedSearch::factory()->for($alert->user)->create();
        $alert->savedSearches()->attach($search);

        $search->delete();

        $this->assertModelExists($alert);
        $this->assertCount(0, $alert->fresh()->savedSearches);
    }

    public function test_notified_at_starts_empty(): void
    {
        $this->assertNull(Alert::factory()->create()->notified_at);
    }
}
