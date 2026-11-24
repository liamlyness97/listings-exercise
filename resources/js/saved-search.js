const CRITERIA = ['property_type', 'region', 'max_price', 'min_bedrooms'];

// A match means the same as the listings page, so a saved search's current
// matches are just the listings page with its criteria in the query string.
export function currentMatchesUrl(search) {
    const criteria = Object.fromEntries(
        CRITERIA.filter((key) => search[key] !== null).map((key) => [key, search[key]]),
    );

    return `/?${new URLSearchParams(criteria)}`;
}
