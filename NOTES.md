# Notes: Saved-search alerts

**Summary:** Buyers can save a search (property type, region, max price, min bedrooms), view and delete their saved searches, and see alerts. When a listing goes live, a queued job matches it against saved searches and creates one alert per user per listing, recording which searches matched. It's in-app only: no emails are sent, but the data model is ready for them. Time Taken: 4-5 hours with a few breaks in between

## Key decisions

- **Trigger: a model observer.** There's no publish step, so I dispatch `ListingWentLive` when a listing is created as live or changes to live. An explicit action would silently miss anything that sets status directly, and a scheduled sweep adds delay. The cost is a hidden side effect on save, which is documented here.
- **A match is what the listings page would show** for the same filters. A blank criterion means "any", and at least one is required. I didn't refactor the controller to share code, because the two queries run in opposite directions. A parity test runs the same searches through both and fails if they disagree.
- **One alert per user per listing**, enforced by a unique index, remembering which searches matched. This makes the job safe to retry and stops a relisted property alerting twice.
- **No backfill.** Saving a search doesn't alert you about existing listings, which would be a burst of alerts and the spam Support warned about. A "View current matches" link covers it.
- **Creating an alert is separate from notifying.** A nullable `notified_at` is the hook for a future digest.
- **The alerts page shows only live listings,** matching the public pages.

## What came up

- **The auth stub only half worked:** `auth()->user()` was null, so middleware and policies treated everyone as a guest. I fixed the stub (set the guard user, register it before the `auth` middleware) instead of working around it, and tests pin both changes.
- **Queue timing bugs** hidden by a synchronous test queue: a search saved while the job waited was alerted, and a listing returned to draft before the job ran used up the user's one alert. Fixed by recording go-live time and skipping listings that are no longer live.
- **SQLite hides MySQL behaviour:** `max_price` had no upper bound (a 500 on MySQL), and SQLite can't catch a missing pagination tiebreaker, so my alerts pagination test asserts exact order.
- **Existing issues raised, not fixed:** an N+1 on the listings index, no visible filter errors, `per_page` lost on search, and no upper bound on the page's `max_price`.

## How I worked with AI

I used Claude Code as a pair I directed and reviewed. Decisions were discussed and logged before code, and when it jumped ahead I stopped it and set an explore-first rule. I worked in small branches and PRs with a review at each checkpoint, and test-first, with mutation checks that caught two of my own tests that couldn't fail. Greptile reviewed each PR, and a fresh reviewer covered the whole branch at the end. I triaged every finding myself.

## Left out

Email delivery and digest (designed, not built), scoring matches, grouping alerts by search, a "matches your saved search" label, a price range, and a shared criteria definition.

## Where it won't hold up yet

- Each new listing is matched against every saved search, so at scale I'd narrow candidates first (for example by region).
- Bulk imports create one job per listing and need batching.
- The alerts table grows forever and needs clean-up and a (`user_id`, `created_at`) index.
- Two identical saves at the same instant can both succeed, which is harmless but untidy.
- Alerts need a queue worker running (documented in the README).

## What I'd do next

1. A daily digest job, with a frequency setting and muting a single search.
2. A price range, after extracting one shared criteria definition.
3. Batching and candidate narrowing for matching.
4. CI against MySQL instead of SQLite.
5. A criteria fingerprint to prevent duplicate searches.
6. Fix the existing issues I raised.
