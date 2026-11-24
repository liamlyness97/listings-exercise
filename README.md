# Street Listings

A small property-listings application: a Laravel 13 API with a Vue 3 front end. It lists properties for sale, organised by branch, with filtering and a detail view. Buyers can save a search and get an alert when a new listing matches it (see [Saved searches and alerts](#saved-searches-and-alerts)).

This repository is the starting point for a technical exercise. **Your task is described in [`TASK.md`](TASK.md).** This README covers what's here and how to run it.

## Requirements

- PHP 8.4
- Composer
- Node 20+ and npm
- SQLite (bundled with PHP — no separate database server needed)

## Getting started

```bash
# 1. Install dependencies
#    composer install also creates .env from .env.example (if missing),
#    creates database/database.sqlite, and sets APP_KEY when it's blank.
composer install
npm install

# 2. Database — migrate and seed
php artisan migrate --seed

# 3. Run — in three terminals, or use the combined dev script (composer dev)
php artisan serve      # API + app at http://127.0.0.1:8000
npm run dev            # Vite dev server for the front end
php artisan queue:work # Queue worker: creates alerts when listings go live
```

Then open <http://127.0.0.1:8000>.

**The queue worker is needed for alerts.** Matching a newly live listing against saved searches runs as a queued job (`QUEUE_CONNECTION=database`). Without a worker, the jobs wait in the `jobs` table and no alerts appear. `composer dev` starts a worker alongside the server and Vite. It also starts `php artisan pail`, which needs the `pcntl` extension, and `pcntl` isn't available on Windows. Because the script stops everything when one process exits, use the three commands above on Windows.

## Running the checks

```bash
php artisan test              # PHPUnit feature/unit tests
vendor/bin/pint --test        # Code style (Laravel Pint)
vendor/bin/phpstan analyse    # Static analysis (Larastan, level 5)
```

All three run in CI (`.github/workflows/ci.yml`) on push and pull request, and all pass on a fresh clone.

If PHPStan stops with *"reached configured PHP memory limit"*, your PHP `memory_limit` is too low for it (128M can be borderline). Run `vendor/bin/phpstan analyse --memory-limit=1G`.

## How it's laid out

```
app/
  Enums/                 PropertyType, ListingStatus
  Events/                ListingWentLive
  Http/
    Controllers/         ListingController, SavedSearchController, AlertController
    Requests/            ListingIndexRequest (filter validation)
                         SavedSearchStoreRequest (saved-search validation)
    Resources/           ListingResource, BranchResource,
                         SavedSearchResource, AlertResource
    Middleware/          ActAsDemoUser (auth stub — see below)
                         HandleInertiaRequests (shared props)
  Listeners/             CreateAlertsForMatchingSavedSearches (queued)
  Models/                Branch, Listing, SavedSearch, Alert
  Observers/             ListingObserver (dispatches ListingWentLive)
  Policies/              SavedSearchPolicy
database/
  factories/             BranchFactory, ListingFactory (with states),
                         SavedSearchFactory, AlertFactory
  migrations/            branches, listings, saved_searches, alerts,
                         alert_saved_search
  seeders/               DatabaseSeeder
resources/js/
  pages/Listings/        Index.vue, Show.vue
  pages/SavedSearches/   Index.vue
  pages/Alerts/          Index.vue
  components/            AppLayout, ListingCard, ListingFilters, Pagination,
                         SaveSearchButton, SavedSearchSummary
  app.js                 Inertia entry point
  format.js              Price and date formatting
  saved-search.js        "Current matches" URL for a saved search
routes/                  web.php
tests/Feature/           ListingPageTest, ListingTest, SavedSearchTest,
                         SavedSearchPageTest, SavedSearchAlertTest,
                         ListingWentLiveTest, AlertTest, AlertPageTest,
                         ActAsDemoUserTest
```

### The domain

- **Branch** — a name and a region.
- **Listing** — address, price, bedrooms, bathrooms, `property_type`, `status` (`draft` / `live` / `under_offer` / `sold`), a branch, and a `listed_at` date.
- **SavedSearch** — a user's set of listings filters: `property_type`, `region`, `max_price`, `min_bedrooms`. Each is optional (blank means "any"), and at least one is required.
- **Alert** — a newly live listing that matched one or more of a user's saved searches. There's one alert per user per listing, linked to every search it matched. `notified_at` is reserved for future email delivery and isn't set yet.

### The stack

Laravel + **Inertia** + Vue 3 + Tailwind — the same shape as our internal apps. There is no separate JSON API: controllers return `Inertia::render(...)` with props, and API Resources (`ListingResource`, `BranchResource`) define the shape those props take.

| Method | Path                 | Page              | Notes                                                      |
| ------ | -------------------- | ----------------- | ---------------------------------------------------------- |
| GET    | `/`                  | `Listings/Index`  | Live listings, paginated. Filters: `property_type`, `max_price`, `min_bedrooms`, `region`, `per_page`. |
| GET    | `/listings/{listing}`| `Listings/Show`   | A single live listing. Non-live listings 404.              |
| GET    | `/saved-searches`    | `SavedSearches/Index` | The current user's saved searches, newest first.       |
| POST   | `/saved-searches`    | —                 | Save a search from the listings filters. Redirects to `/saved-searches`. |
| DELETE | `/saved-searches/{savedSearch}` | —      | Delete one of your own saved searches (others get a 403). |
| GET    | `/alerts`            | `Alerts/Index`    | The current user's alerts for listings still live, newest first, paginated. |

The saved-search and alert routes sit behind the `auth` middleware. Guests are redirected to `/`, because there's no login page while auth is stubbed.

Filters live in the query string, so a search is shareable, bookmarkable and survives the back button. `pages/Listings/Index.vue` seeds its form from the `filters` prop and re-issues a `router.get` on submit.

### Saved searches and alerts

1. **Save a search.** Apply filters on the listings page and press **Save this search**. It saves the filters you searched with, not any unsubmitted changes in the form.
2. **A listing goes live.** `ListingObserver` dispatches `ListingWentLive` when a listing is created as live, or its status changes to live. Edits to a listing that's already live don't count.
3. **Matching.** The queued `CreateAlertsForMatchingSavedSearches` listener finds the saved searches the listing satisfies, using the same rules as the listings filters. It creates one alert per user, linked to every search that matched.
4. **See alerts.** **Alerts** in the header lists them, with links to each listing and to each matched search's current results.

A few rules:
- **No backfill.** A saved search is only alerted about listings that go live after it was saved. Use **View current matches** to see what's already on the market.
- **No duplicates.** A listing matching several searches gives one alert, and a relisted property doesn't alert again.
- **Only live listings show on the alerts page.** An alert reappears if its listing is relisted.

To try it locally, run the queue worker (see [Getting started](#getting-started)) and save a search for the area of a draft listing. Then make that listing live in `php artisan tinker`:

```php
$listing = App\Models\Listing::where('status', 'draft')->first();
$listing->branch->region;   // save a search for this area in the browser first
$listing->update(['status' => App\Enums\ListingStatus::Live, 'listed_at' => now()]);
```

The alert appears on **Alerts** once the worker has processed the job.

### Authentication

Auth is **stubbed**. A real deployment would authenticate requests properly; to keep this exercise focused on the feature rather than on auth plumbing, every request is resolved as the seeded demo user (`demo@street.example`) via `App\Http\Middleware\ActAsDemoUser`, and shared to the front end as the `auth.user` prop. Build any user-scoped work against `$request->user()` / `auth()->user()` as you normally would — it will return the demo user.

The stub sets the user on the auth guard, and runs ahead of the `auth` middleware, so `$request->user()`, `auth()->user()`, the `auth` middleware and policies all see the demo user. (Originally it only replaced the request's user resolver, which left `auth()->user()` returning `null`. `ActAsDemoUserTest` now covers this.)

Note no user is resolved until the database is seeded, so run `php artisan migrate --seed` before you start.

---

Your task: **[`TASK.md`](TASK.md)**.
