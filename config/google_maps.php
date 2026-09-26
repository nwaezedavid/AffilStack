<?php

/**
 * Core feature 7 (Phase 2): Google Maps local lead-finding, feeding the
 * existing CRM (config('crm') doesn't exist as its own file — CrmContact
 * already had `source`/`raw_data` columns from Phase 1, unused until now).
 *
 * Uses the Google Places API directly over HTTP (Laravel's own HTTP client
 * — no new composer dependency) rather than a Google Maps SDK package, per
 * the project's no-new-dependencies-without-approval rule.
 */
return [
    // The credential itself lives in config('services.google_places.api_key')
    // — already reserved there (alongside Flutterwave) from earlier
    // planning, following this app's own convention that config/services.php
    // is where third-party API credentials live. This file holds this
    // feature's own policy knobs instead.

    // Places API (New). The legacy /maps/api/place/* endpoints this used
    // first can't be enabled on any Google Cloud project created after
    // 2025-03-01, so a fresh production key would fail every search.
    // Text Search returns name/address/rating/place id for a free-text
    // query like "dentists in Austin, TX" — no phone/website. Those live
    // behind a second, per-place Details call, made only for a place the
    // user actually chooses to import (see GoogleMapsLeadService::details())
    // rather than once per search result, to keep a single search to one
    // Places API call regardless of how many results it returns.
    'search_endpoint' => 'https://places.googleapis.com/v1/places:searchText',
    'details_endpoint' => 'https://places.googleapis.com/v1/places',

    'results_limit' => 20,
];
