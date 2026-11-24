<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_type_is_cast_to_the_enum(): void
    {
        $search = SavedSearch::factory()->create(['property_type' => PropertyType::Flat]);

        $this->assertSame(PropertyType::Flat, $search->refresh()->property_type);
    }

    public function test_blank_criteria_are_stored_as_null(): void
    {
        $search = SavedSearch::factory()->create([
            'region' => 'Leeds',
            'max_price' => null,
        ])->refresh();

        $this->assertSame('Leeds', $search->region);
        $this->assertNull($search->property_type);
        $this->assertNull($search->max_price);
        $this->assertNull($search->min_bedrooms);
    }

    public function test_a_users_saved_searches_are_deleted_with_the_user(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory(2)->for($user)->create();

        $user->delete();

        $this->assertSame(0, SavedSearch::count());
    }
}
