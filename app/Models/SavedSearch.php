<?php

namespace App\Models;

use App\Enums\PropertyType;
use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A set of listings-index filters a user wants to be alerted about. A null
 * criterion means "any".
 *
 * @property int $id
 * @property int $user_id
 * @property PropertyType|null $property_type
 * @property string|null $region
 * @property int|null $max_price
 * @property int|null $min_bedrooms
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Collection<int, Alert> $alerts
 */
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    /**
     * user_id is deliberately absent: searches are created through
     * $user->savedSearches(), so a request can never assign one to someone else.
     */
    protected $fillable = [
        'property_type',
        'region',
        'max_price',
        'min_bedrooms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'max_price' => 'integer',
            'min_bedrooms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Alert, $this>
     */
    public function alerts(): BelongsToMany
    {
        return $this->belongsToMany(Alert::class);
    }
}
