<?php

namespace App\Http\Requests;

use App\Enums\PropertyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class SavedSearchStoreRequest extends FormRequest
{
    /**
     * The same criteria as the listings index filters (ListingIndexRequest).
     */
    private const CRITERIA = ['property_type', 'region', 'max_price', 'min_bedrooms'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * "0+ beds" matches every listing: it means "any", the same as blank. Left
     * as a value it would count as a criterion and let an alert-on-everything
     * search through.
     */
    protected function prepareForValidation(): void
    {
        if ((string) $this->input('min_bedrooms') === '0') {
            $this->merge(['min_bedrooms' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'property_type' => ['nullable', new Enum(PropertyType::class)],
            'region' => ['nullable', 'string', 'max:100'],
            // The upper bound is the largest value the unsigned integer column
            // holds; anything bigger would be a database error, not a field error.
            'max_price' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'min_bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
        ];
    }

    /**
     * An empty search would alert on every new listing, so at least one
     * criterion is required.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $hasCriterion = collect(self::CRITERIA)->contains(fn (string $key) => $this->filled($key));

                if (! $hasCriterion) {
                    $validator->errors()->add('criteria', 'Choose at least one filter before saving a search.');
                }
            },
        ];
    }

    /**
     * All four criteria, null when not given. Always returning every key
     * matters: looking up an existing search by only the keys present would
     * treat a narrower search as a duplicate of a broader one.
     *
     * @return array{property_type: ?string, region: ?string, max_price: ?int, min_bedrooms: ?int}
     */
    public function criteria(): array
    {
        return [
            'property_type' => $this->validated('property_type'),
            'region' => $this->validated('region'),
            'max_price' => $this->filled('max_price') ? $this->integer('max_price') : null,
            'min_bedrooms' => $this->filled('min_bedrooms') ? $this->integer('min_bedrooms') : null,
        ];
    }
}
