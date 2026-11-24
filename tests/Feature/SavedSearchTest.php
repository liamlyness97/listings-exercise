<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_matching_finds_a_search_whose_criteria_all_hold(): void
    {
        $listing = $this->manchesterFlat();
        $search = SavedSearch::factory()->create([
            'property_type' => PropertyType::Flat,
            'region' => 'Manchester',
            'max_price' => 300_000,
            'min_bedrooms' => 2,
        ]);

        $this->assertTrue($this->searchMatches($search, $listing));
    }

    public function test_blank_criteria_match_any_value(): void
    {
        $listing = $this->manchesterFlat();
        $search = SavedSearch::factory()->create(['region' => 'Manchester', 'max_price' => null]);

        $this->assertTrue($this->searchMatches($search, $listing));
    }

    /**
     * Same comparisons as the listings page: price at or under the max,
     * bedrooms at or over the min.
     */
    public function test_matching_includes_exact_boundaries(): void
    {
        $listing = $this->manchesterFlat();
        $search = SavedSearch::factory()->create(['max_price' => 250_000, 'min_bedrooms' => 3]);

        $this->assertTrue($this->searchMatches($search, $listing));
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    #[DataProvider('failingCriteria')]
    public function test_a_single_failing_criterion_excludes_the_search(array $criteria): void
    {
        $listing = $this->manchesterFlat();
        $search = SavedSearch::factory()->create([
            'property_type' => PropertyType::Flat,
            'region' => 'Manchester',
            'max_price' => 300_000,
            'min_bedrooms' => 2,
            ...$criteria,
        ]);

        $this->assertFalse($this->searchMatches($search, $listing));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function failingCriteria(): array
    {
        return [
            'wrong type' => [['property_type' => PropertyType::Detached]],
            'wrong region' => [['region' => 'Leeds']],
            'over budget' => [['max_price' => 249_999]],
            'too few bedrooms' => [['min_bedrooms' => 4]],
        ];
    }

    /**
     * A saved search promises the same results as the listings page with the
     * same filters. The two use separate queries (the page filters listings,
     * the matcher finds searches for one listing), so this guards against them
     * drifting apart.
     */
    public function test_matching_agrees_with_the_listings_page(): void
    {
        $manchester = Branch::factory()->create(['region' => 'Manchester']);
        $leeds = Branch::factory()->create(['region' => 'Leeds']);

        $listings = collect([
            [$manchester, PropertyType::Flat, 180_000, 1],
            [$manchester, PropertyType::Flat, 250_000, 2],
            [$manchester, PropertyType::Detached, 450_000, 4],
            [$manchester, PropertyType::Terraced, 300_000, 3],
            [$leeds, PropertyType::Flat, 200_000, 2],
            [$leeds, PropertyType::Detached, 600_000, 5],
            [$leeds, PropertyType::SemiDetached, 320_000, 3],
            [$leeds, PropertyType::Bungalow, 280_000, 2],
        ])->map(fn (array $row) => Listing::factory()->live()->for($row[0])->create([
            'property_type' => $row[1],
            'price' => $row[2],
            'bedrooms' => $row[3],
        ]));

        $searches = collect([
            ['region' => 'Manchester'],
            ['property_type' => 'flat'],
            ['max_price' => 300_000],
            ['min_bedrooms' => 3],
            ['region' => 'Leeds', 'max_price' => 320_000, 'min_bedrooms' => 2],
            ['property_type' => 'flat', 'region' => 'Manchester', 'max_price' => 250_000, 'min_bedrooms' => 2],
        ]);

        foreach ($searches as $criteria) {
            $search = SavedSearch::factory()->create([
                'property_type' => null,
                'region' => null,
                'max_price' => null,
                'min_bedrooms' => null,
                ...$criteria,
            ]);

            $pageIds = collect(
                $this->get('/?'.http_build_query([...$criteria, 'per_page' => 100]))
                    ->viewData('page')['props']['listings']['data']
            )->pluck('id')->all();

            $matcherIds = $listings
                ->filter(fn (Listing $listing) => $this->searchMatches($search, $listing))
                ->pluck('id')
                ->all();

            $this->assertNotEmpty($pageIds, 'Each search should match something, or the comparison proves little.');
            $this->assertEqualsCanonicalizing($pageIds, $matcherIds, 'Mismatch for '.json_encode($criteria));
        }
    }

    private function manchesterFlat(): Listing
    {
        return Listing::factory()
            ->live()
            ->for(Branch::factory()->create(['region' => 'Manchester']))
            ->create([
                'property_type' => PropertyType::Flat,
                'price' => 250_000,
                'bedrooms' => 3,
            ]);
    }

    private function searchMatches(SavedSearch $search, Listing $listing): bool
    {
        return SavedSearch::query()->matching($listing)->whereKey($search->id)->exists();
    }
}
