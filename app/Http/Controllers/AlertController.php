<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Http\Resources\AlertResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    /**
     * The current user's alerts, newest first. Only listings that are still
     * live are shown, matching the public listing pages; the alert records are
     * kept, so a relisted property appears again.
     */
    public function index(Request $request): Response
    {
        // The same rule as Listing::scopeLive(). whereRelation is used because
        // static analysis can't see model scopes inside a whereHas() closure.
        $alerts = $request->user()->alerts()
            ->whereRelation('listing', 'status', ListingStatus::Live)
            // Eager loaded: each alert renders its listing, branch and matched searches.
            ->with(['listing.branch', 'savedSearches'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        return Inertia::render('Alerts/Index', [
            'alerts' => AlertResource::collection($alerts),
        ]);
    }
}
