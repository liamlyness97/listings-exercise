<?php

namespace Database\Factories;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    /**
     * A saved search needs at least one criterion, so the default sets a budget
     * and leaves the rest as "any". Tests override what they care about.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'property_type' => null,
            'region' => null,
            'max_price' => fake()->numberBetween(150, 900) * 1000,
            'min_bedrooms' => null,
        ];
    }
}
