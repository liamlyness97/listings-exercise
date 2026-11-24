<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavedSearchStoreRequest;
use App\Http\Resources\SavedSearchResource;
use App\Models\SavedSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SavedSearchController extends Controller
{
    /**
     * The current user's saved searches, newest first.
     */
    public function index(Request $request): Response
    {
        // `id` is a tiebreaker for searches saved in the same second.
        $savedSearches = $request->user()->savedSearches()
            ->latest()
            ->orderByDesc('id')
            ->get();

        return Inertia::render('SavedSearches/Index', [
            'savedSearches' => SavedSearchResource::collection($savedSearches),
        ]);
    }

    /**
     * Saving the same criteria twice keeps a single search. Existing live
     * listings don't produce alerts; only listings that go live from now on do.
     */
    public function store(SavedSearchStoreRequest $request): RedirectResponse
    {
        $request->user()->savedSearches()->firstOrCreate($request->criteria());

        return to_route('saved-searches.index');
    }

    /**
     * Ownership is checked by SavedSearchPolicy on the route. The search's
     * existing alerts are kept; only their link to it goes.
     */
    public function destroy(SavedSearch $savedSearch): RedirectResponse
    {
        $savedSearch->delete();

        return to_route('saved-searches.index');
    }
}
