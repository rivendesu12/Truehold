<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Property;
use App\Models\PropertyFromSheet;
use App\Models\Client;
use App\Services\PropertyGoogleSheetsService;
use App\Services\ScrapedListingsApiService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class PropertyController extends Controller
{
    /**
     * Filter keys allowed to be tokenized/shared.
     *
     * @var array<int, string>
     */
    protected array $shareableFilterKeys = [
        'location',
        'property_type',
        'min_price',
        'max_price',
        'available_date',
        'management_company',
        'couples_allowed',
        'ensuite',
        'agent_name',
        'paying_only',
        'room_count',
    ];

    /**
     * Get the Google Sheets service only when properties spreadsheet is configured.
     * This avoids loading the Google API client when not using Sheets.
     */
    protected function getSheetsService(): ?PropertyGoogleSheetsService
    {
        if (empty(config('services.google.properties.spreadsheet_id'))) {
            return null;
        }
        return app(PropertyGoogleSheetsService::class);
    }

    /**
     * Resolve the property feed. The Harbor Ops scraped-listings API is the
     * main feed; Google Sheets remains as a fallback while migrating off it.
     * Returns null when neither is configured (database fallback).
     *
     * @return ScrapedListingsApiService|PropertyGoogleSheetsService|null
     */
    /**
     * Property-type buckets and radius search.
     *
     * Types arrive as property_types[] (full_property / studio / rooms). Radius
     * needs a centre: rather than geocode the typed area, take the centroid of
     * the listings whose location already matches it — no API call, and it lands
     * exactly where our own stock is.
     */
    protected function applyTypeAndRadius($request, array &$filters, $feedService): void
    {
        $types = array_values(array_filter((array) $request->input('property_types', [])));
        if ($types) {
            $filters['property_types'] = $types;
        }

        $radius = (float) $request->input('radius_miles', 0);
        if ($radius <= 0 || ! $request->filled('location') || ! $feedService) {
            return;
        }

        try {
            $term = strtolower(trim((string) $request->location));
            $matches = $feedService->getAllProperties()->filter(function ($p) use ($term) {
                return str_contains(strtolower((string) ($p['location'] ?? '')), $term)
                    || str_contains(strtolower((string) ($p['postcode'] ?? '')), $term)
                    || str_contains(strtolower((string) ($p['title'] ?? '')), $term);
            })->filter(fn ($p) => is_numeric($p['latitude'] ?? null) && is_numeric($p['longitude'] ?? null));

            if ($matches->isEmpty()) {
                return; // nothing to centre on; fall back to the text match
            }

            $filters['radius_center'] = [
                (float) $matches->avg(fn ($p) => (float) $p['latitude']),
                (float) $matches->avg(fn ($p) => (float) $p['longitude']),
            ];
            $filters['radius_miles'] = $radius;

            // Radius supersedes the text match, otherwise it could only ever
            // narrow the same area rather than widen around it.
            unset($filters['location']);
        } catch (\Throwable $e) {
            \Log::warning('Radius filter failed', ['error' => $e->getMessage()]);
        }
    }

    protected function getFeedService()
    {
        if (ScrapedListingsApiService::isConfigured()) {
            return app(ScrapedListingsApiService::class);
        }
        return $this->getSheetsService();
    }

    /**
     * Resolve tokenized filters (f=...) into the request query.
     * Existing query params win over token values.
     */
    protected function applyTokenizedFilters(Request $request): void
    {
        $token = trim((string) $request->query('f', ''));
        if ($token === '') {
            return;
        }

        $cacheKey = "property_filter_token:{$token}";
        $stored = Cache::get($cacheKey);
        if (!is_array($stored)) {
            return;
        }
        $request->attributes->set('shared_filter_token', true);

        $merge = [];
        foreach ($this->shareableFilterKeys as $key) {
            if (!$request->filled($key) && isset($stored[$key]) && $stored[$key] !== '') {
                $merge[$key] = (string) $stored[$key];
            }
        }

        if (!empty($merge)) {
            $request->merge($merge);
        }
    }

    /**
     * Restricted filters are normally auth-only, but we allow them when
     * a valid shared token is being resolved.
     */
    protected function canUseRestrictedFilters(Request $request): bool
    {
        return auth()->check() || (bool) $request->attributes->get('shared_filter_token', false);
    }

    /**
     * Session key used to remember the visitor's active listing filters so they
     * persist across every navigation (list <-> map, detail -> back, nav links).
     */
    protected string $filterSessionKey = 'th_property_filters';

    /**
     * Make filters sticky across navigation.
     *
     * - Requests that express a filter state (any filter value, or the qf=1
     *   marker emitted by in-app filter controls) are authoritative: we store
     *   exactly what they carry (an empty set clears the memory).
     * - "Bare" navigations (nav links, the detail-page breadcrumb, a plain
     *   visit) carry no filter state, so we restore the remembered filters by
     *   redirecting to the same route with them applied.
     * - reset=1 forgets everything and returns a clean URL.
     *
     * @return \Illuminate\Http\RedirectResponse|null  redirect to apply/clear, or null to continue
     */
    protected function applyStickyFilters(Request $request, string $routeName)
    {
        // Shared tokens (f=...) are self-contained and shareable — don't interfere.
        if ($request->filled('f')) {
            return null;
        }

        $session = $request->session();

        // Explicit reset: forget remembered filters and land on a clean URL.
        if ($request->boolean('reset')) {
            $session->forget($this->filterSessionKey);
            return redirect()->route($routeName);
        }

        $keys = $this->shareableFilterKeys;

        // Does this request explicitly express a filter state?
        $expressesFilters = $request->has('qf');
        if (!$expressesFilters) {
            foreach ($keys as $key) {
                if ($request->filled($key)) {
                    $expressesFilters = true;
                    break;
                }
            }
        }

        if ($expressesFilters) {
            // Authoritative — remember exactly what this request carries.
            $store = [];
            foreach ($keys as $key) {
                if ($request->filled($key)) {
                    $store[$key] = $request->input($key);
                }
            }

            if (empty($store)) {
                $session->forget($this->filterSessionKey);
            } else {
                $session->put($this->filterSessionKey, $store);
            }

            return null;
        }

        // Bare navigation — restore remembered filters, if any.
        $store = $session->get($this->filterSessionKey, []);
        if (!empty($store) && is_array($store)) {
            $params = array_merge($store, ['qf' => 1]);
            if ($request->filled('layout')) {
                $params['layout'] = $request->query('layout');
            }
            return redirect()->route($routeName, $params);
        }

        return null;
    }

    /**
     * Create a tokenized share URL for current filters.
     */
    public function createShareToken(Request $request)
    {
        $filters = $request->input('filters', []);
        if (!is_array($filters)) {
            $filters = [];
        }

        $sanitized = [];
        foreach ($this->shareableFilterKeys as $key) {
            if (!array_key_exists($key, $filters)) {
                continue;
            }
            $value = $filters[$key];
            if (is_array($value) || is_object($value) || $value === null) {
                continue;
            }
            $value = trim((string) $value);
            if ($value !== '') {
                $sanitized[$key] = Str::limit($value, 255, '');
            }
        }

        $token = Str::random(16);
        Cache::put("property_filter_token:{$token}", $sanitized, now()->addDays(30));

        $view = $request->input('view', 'listing') === 'map' ? 'map' : 'listing';
        $baseUrl = $view === 'map' ? route('properties.map') : route('properties.index');

        return response()->json([
            'token' => $token,
            'share_url' => $baseUrl . '?f=' . $token,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->applyTokenizedFilters($request);
        if ($redirect = $this->applyStickyFilters($request, 'properties.index')) {
            return $redirect;
        }
        $canUseRestrictedFilters = $this->canUseRestrictedFilters($request);

        // Main feed: Harbor Ops scraped-listings API, then Google Sheets fallback
        $feedService = $this->getFeedService();

        // Force clear cache if requested (for debugging)
        if ($request->has('clear_cache') && $feedService) {
            $feedService->clearCache();
            \Log::info('Properties cache manually cleared');
        }

        if ($feedService) {
            try {
                // Build filters array
                $filters = [];
                
                if ($request->filled('location')) {
                    $filters['location'] = $request->location;
                }

                if ($request->filled('min_price')) {
                    $filters['min_price'] = $request->min_price;
                }
                
                if ($request->filled('max_price')) {
                    $filters['max_price'] = $request->max_price;
                }

                if ($request->filled('property_type')) {
                    $filters['property_type'] = $request->property_type;
                }

                $this->applyTypeAndRadius($request, $filters, $feedService);

                if ($request->filled('available_date')) {
                    $filters['available_date'] = $request->available_date;
                }

                if ($request->filled('management_company')) {
                    $filters['management_company'] = $request->management_company;
                }

                if ($request->filled('agent_name') && $canUseRestrictedFilters) {
                    $filters['agent_name'] = $request->agent_name;
                }

                if ($request->filled('paying_only') && $canUseRestrictedFilters) {
                    $filters['paying_only'] = true;
                    $filters['allow_restricted'] = true;
                }

                if ($request->filled('couples_allowed')) {
                    $filters['couples_allowed'] = $request->couples_allowed;
                }

                if ($request->filled('ensuite')) {
                    $filters['ensuite'] = $request->ensuite;
                }

                if ($request->filled('room_count')) {
                    $filters['room_count'] = $request->room_count;
                }

                // Get filtered properties from the feed
                $filteredProperties = $feedService->filterProperties($filters);

                // Get filter values for dropdowns
                $filterValues = $feedService->getFilterValues();
                $locations = $filterValues['locations'];
                $propertyTypes = $filterValues['propertyTypes'];
                $availableDates = $filterValues['available_dates'];
                $agentNames = $filterValues['agent_names'];
                $agentsWithPaying = $filterValues['agents_with_paying'];
                $roomCounts = $filterValues['room_counts'];

                // Convert properties to Property-like objects for view compatibility
                $properties = $filteredProperties->map(function ($propertyData) {
                    return new PropertyFromSheet($propertyData);
                });

                // Sort properties: Premium first, then by ID
                $properties = $properties->sort(function ($a, $b) {
                    // Check if property has "Premium" flag (case-insensitive)
                    $aIsPremium = !empty($a->flag) && strtolower($a->flag) === 'premium';
                    $bIsPremium = !empty($b->flag) && strtolower($b->flag) === 'premium';
                    
                    // If both are premium or both are not premium, sort newest first.
                    // API rows carry created_at; sheet rows fall back to ID descending.
                    if ($aIsPremium === $bIsPremium) {
                        if (!empty($a->created_at) && !empty($b->created_at)) {
                            return strcmp((string) $b->created_at, (string) $a->created_at);
                        }
                        return $b->id <=> $a->id;
                    }
                    
                    // Premium properties come first
                    return $bIsPremium <=> $aIsPremium;
                })->values();

                // Paginate the results
                $perPage = 20;
                $currentPage = $request->get('page', 1);
                $items = $properties->slice(($currentPage - 1) * $perPage, $perPage)->values();
                
                $properties = new LengthAwarePaginator(
                    $items,
                    $properties->count(),
                    $perPage,
                    $currentPage,
                    ['path' => $request->url(), 'query' => $request->query()]
                );

        // Log filter results for debugging
        \Log::info('Property index filters applied (feed)', [
            'filters' => $filters,
            'total_results' => $properties->total(),
            'current_page' => $properties->currentPage(),
            'per_page' => $properties->perPage(),
            'data_source' => class_basename($feedService),
        ]);

        return view('properties.index', compact('properties', 'locations', 'propertyTypes', 'availableDates', 'agentNames', 'agentsWithPaying', 'roomCounts'));
            } catch (\Exception $e) {
                \Log::error('Error loading properties from feed, falling back to database', [
                    'data_source' => class_basename($feedService),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Fall through to database fallback
            }
        }
        
        // Fallback to database if no feed configured or the feed fails
        $query = Property::query();

        // Apply filters
        if ($request->filled('location')) {
            $query->byLocation($request->location);
        }

        // Price filtering - handle min and max separately
        if ($request->filled('min_price')) {
            $query->byMinPrice($request->min_price);
        }
        
        if ($request->filled('max_price')) {
            $query->byMaxPrice($request->max_price);
        }

        if ($request->filled('property_type')) {
            $query->where('property_type', 'like', "%{$request->property_type}%");
        }

        if ($request->filled('available_date')) {
            $query->where('available_date', 'like', "%{$request->available_date}%");
        }

        // New filters: Management Company, Agent Name
        if ($request->filled('management_company')) {
            $query->byManagementCompany($request->management_company);
        }

        // Agent name filtering - only for authenticated users
        if ($request->filled('agent_name') && $canUseRestrictedFilters) {
            $query->where('agent_name', 'like', "%{$request->agent_name}%");
        }

        // Paying agents only (auth or resolved shared token)
        if ($request->filled('paying_only') && $canUseRestrictedFilters) {
            $query->whereRaw("LOWER(TRIM(COALESCE(paying, ''))) = 'yes'");
        }

        if ($request->filled('couples_allowed')) {
            $query->byCouplesAllowed($request->couples_allowed);
        }

        if ($request->filled('ensuite')) {
            $query->byEnsuite($request->ensuite);
        }

        if ($request->filled('room_count')) {
            $query->where('total_rooms', $request->room_count);
        }

        // Get unique values for filter dropdowns
        $locations = Property::distinct()->pluck('location')->filter()->sort()->values();
        $propertyTypes = Property::distinct()->pluck('property_type')->filter()->sort()->values();
        $availableDates = Property::distinct()->pluck('available_date')->filter()->sort()->values();
        $roomCounts = Property::distinct()->pluck('total_rooms')->filter()->sort()->values();
        
        // Get agent names with paying status - only for authenticated users
        if (auth()->check()) {
            $agentNames = Property::distinct()->pluck('agent_name')->filter()->sort()->values();
            // Get agents with paying status
            $agentsWithPaying = Property::whereNotNull('agent_name')
                ->where('agent_name', '!=', '')
                ->select('agent_name', 'paying')
                ->get()
                ->groupBy('agent_name')
                ->map(function ($properties) {
                    // Check if any property for this agent has paying = 'yes'
                    return $properties->contains(function ($property) {
                        return strtolower($property->paying ?? '') === 'yes';
                    });
                });
        } else {
            $agentNames = collect(); // Empty collection for non-authenticated users
            $agentsWithPaying = collect();
        }

        // Sort by premium flag first, then by latest
        $properties = $query
            ->orderByRaw("CASE WHEN LOWER(flag) = 'premium' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(20);

        // Log filter results for debugging
        \Log::info('Property index filters applied (Database Fallback)', [
            'filters' => $request->all(),
            'total_results' => $properties->total(),
            'current_page' => $properties->currentPage(),
            'per_page' => $properties->perPage(),
            'data_source' => 'database',
            'google_sheets_configured' => !empty(config('services.google.properties.spreadsheet_id')),
        ]);

        return view('properties.index', compact('properties', 'locations', 'propertyTypes', 'availableDates', 'agentNames', 'agentsWithPaying', 'roomCounts'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Try the configured property feed first (API or Google Sheets)
        $feedService = $this->getFeedService();

        if ($feedService) {
            try {
                $propertyData = $feedService->getPropertyById($id);
                
                if ($propertyData) {
                    $property = new PropertyFromSheet($propertyData);
                    
                    // Load interests from database if they exist (hybrid approach)
                    // Note: This assumes property interests are still stored in DB
                    $propertyId = $property->id;
                    $interests = \App\Models\PropertyInterest::where('property_id', $propertyId)
                        ->with('client')
                        ->get();
                    
                    $property->setRelation('interests', $interests);
                    
                    $interestedClients = $interests->map(function ($interest) {
                        return $interest->client;
                    })->filter();
                    
                    $property->setRelation('interestedClients', $interestedClients);
                    
                    $clients = collect();
                    if (auth()->check()) {
                        $clients = Client::orderBy('full_name', 'asc')->get();
                    }
                    
                    return view('properties.show', compact('property', 'clients'));
                }
            } catch (\Exception $e) {
                \Log::error('Error loading property from feed, falling back to database', [
                    'id' => $id,
                    'data_source' => class_basename($feedService),
                    'error' => $e->getMessage()
                ]);
                // Fall through to database fallback
            }
        }

        // Fallback to database if not found in the feed or no feed configured
        $property = Property::with(['interests.client', 'interestedClients'])->findOrFail($id);
        
        $clients = collect();
        if (auth()->check()) {
            $clients = Client::orderBy('full_name', 'asc')->get();
        }
        
        return view('properties.show', compact('property', 'clients'));
    }

    /**
     * Display properties on an interactive map.
     */
    public function map(Request $request)
    {
        $this->applyTokenizedFilters($request);
        if ($redirect = $this->applyStickyFilters($request, 'properties.map')) {
            return $redirect;
        }
        $canUseRestrictedFilters = $this->canUseRestrictedFilters($request);

        // Main feed: Harbor Ops scraped-listings API, then Google Sheets fallback
        $feedService = $this->getFeedService();

        if ($feedService) {
            try {
                // Build filters array
                $filters = [];
                
                if ($request->filled('location')) {
                    $filters['location'] = $request->location;
                }

                if ($request->filled('min_price')) {
                    $filters['min_price'] = $request->min_price;
                }
                
                if ($request->filled('max_price')) {
                    $filters['max_price'] = $request->max_price;
                }

                if ($request->filled('property_type')) {
                    $filters['property_type'] = $request->property_type;
                }

                $this->applyTypeAndRadius($request, $filters, $feedService);

                if ($request->filled('available_date')) {
                    $filters['available_date'] = $request->available_date;
                }

                if ($request->filled('management_company')) {
                    $filters['management_company'] = $request->management_company;
                }

                if ($request->filled('agent_name') && $canUseRestrictedFilters) {
                    $filters['agent_name'] = $request->agent_name;
                }

                if ($request->filled('paying_only') && $canUseRestrictedFilters) {
                    $filters['paying_only'] = true;
                    $filters['allow_restricted'] = true;
                }

                if ($request->filled('couples_allowed')) {
                    $filters['couples_allowed'] = $request->couples_allowed;
                }

                if ($request->filled('ensuite')) {
                    $filters['ensuite'] = $request->ensuite;
                }

                if ($request->filled('room_count')) {
                    $filters['room_count'] = $request->room_count;
                }

                // Get filtered properties from the feed
                $filteredProperties = $feedService->filterProperties($filters);
                
                // Filter properties with valid coordinates
                $properties = $filteredProperties->filter(function ($property) {
                    $lat = $property['latitude'] ?? null;
                    $lng = $property['longitude'] ?? null;
                    
                    // Handle string coordinates
                    if (is_string($lat)) {
                        $lat = str_replace(',', '.', trim($lat));
                    }
                    if (is_string($lng)) {
                        $lng = str_replace(',', '.', trim($lng));
                    }
                    
                    if (empty($lat) || empty($lng) || $lat === 'N/A' || $lng === 'N/A') {
                        return false;
                    }
                    
                    $latFloat = (float) $lat;
                    $lngFloat = (float) $lng;
                    
                    // Check if valid numeric values
                    if (!is_numeric($lat) || !is_numeric($lng)) {
                        return false;
                    }
                    
                    return (-90 <= $latFloat && $latFloat <= 90) && (-180 <= $lngFloat && $lngFloat <= 180);
                });
                
                \Log::info('Properties filtered by coordinates', [
                    'before_filter' => $filteredProperties->count(),
                    'after_filter' => $properties->count(),
                    'sample_properties' => $properties->take(3)->map(function($p) {
                        return [
                            'id' => $p['id'] ?? 'NO ID',
                            'title' => $p['title'] ?? 'NO TITLE',
                            'lat' => $p['latitude'] ?? null,
                            'lng' => $p['longitude'] ?? null,
                            'lat_type' => gettype($p['latitude'] ?? null),
                            'lng_type' => gettype($p['longitude'] ?? null),
                        ];
                    })
                ]);

                // Sort properties: Premium first, then by ID
                $properties = $properties->sort(function ($a, $b) {
                    // Check if property has "Premium" flag (case-insensitive)
                    $aIsPremium = !empty($a['flag']) && strtolower($a['flag']) === 'premium';
                    $bIsPremium = !empty($b['flag']) && strtolower($b['flag']) === 'premium';
                    
                    // If both are premium or both are not premium, sort newest first.
                    // API rows carry created_at; sheet rows fall back to ID descending.
                    if ($aIsPremium === $bIsPremium) {
                        if (!empty($a['created_at']) && !empty($b['created_at'])) {
                            return strcmp((string) $b['created_at'], (string) $a['created_at']);
                        }
                        return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
                    }
                    
                    // Premium properties come first
                    return $bIsPremium <=> $aIsPremium;
                })->values();

                // Convert to Property-like objects for view compatibility
                $propertyObjects = $properties->map(function ($propertyData) {
                    return new PropertyFromSheet($propertyData);
                })->take(400);

                // For JSON encoding in the view, convert back to arrays with proper coordinate types
                $propertiesForJson = $properties->map(function ($propertyData) {
                    // Ensure coordinates are properly formatted as numbers
                    $propertyData['latitude'] = isset($propertyData['latitude']) ? (float) $propertyData['latitude'] : null;
                    $propertyData['longitude'] = isset($propertyData['longitude']) ? (float) $propertyData['longitude'] : null;
                    // Ensure description is included for filtering
                    if (!isset($propertyData['description'])) {
                        $propertyData['description'] = '';
                    }
                    // Ensure couples fields are included for filtering
                    if (!isset($propertyData['couples_ok'])) {
                        $propertyData['couples_ok'] = '';
                    }
                    if (!isset($propertyData['couples_allowed'])) {
                        $propertyData['couples_allowed'] = '';
                    }
                    if (!isset($propertyData['total_rooms'])) {
                        $propertyData['total_rooms'] = $propertyData['room_count'] ?? '';
                    }
                    if (!isset($propertyData['room_count'])) {
                        $propertyData['room_count'] = $propertyData['total_rooms'] ?? '';
                    }
                    return $propertyData;
                })->take(400)->values()->all();

                // Get filter values for dropdowns
                $filterValues = $feedService->getFilterValues();
                $locations = $filterValues['locations'];
                $propertyTypes = $filterValues['propertyTypes'];
                $availableDates = $filterValues['available_dates'];
                $agentNames = $filterValues['agent_names'];
                $agentsWithPaying = $filterValues['agents_with_paying'];
                $roomCounts = $filterValues['room_counts'];

                // Log validation results for debugging
                \Log::info('Map query results (feed)', [
                    'data_source' => class_basename($feedService),
                    'total_properties' => $propertyObjects->count(),
                    'properties_with_coords' => collect($propertiesForJson)->filter(function($p) {
                        return !empty($p['latitude']) && !empty($p['longitude']);
                    })->count(),
                    'filters_applied' => $filters,
                    'sample_coords' => collect($propertiesForJson)->take(3)->map(function($p) {
                        return [
                            'id' => $p['id'] ?? 'NO ID',
                            'lat' => $p['latitude'] ?? null,
                            'lng' => $p['longitude'] ?? null
                        ];
                    })->values()->all()
                ]);

                return view('properties.map', [
                    'properties' => $propertyObjects,
                    'propertiesForJson' => $propertiesForJson,
                    'locations' => $locations,
                    'propertyTypes' => $propertyTypes,
                    'availableDates' => $availableDates,
                    'agentNames' => $agentNames,
                    'agentsWithPaying' => $agentsWithPaying,
                    'roomCounts' => $roomCounts
                ]);
            } catch (\Exception $e) {
                \Log::error('Error loading properties from feed for map, falling back to database', [
                    'data_source' => class_basename($feedService),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Fall through to database fallback
            }
        }

        // Fallback to database if no feed configured or the feed fails
        $query = Property::query()->withValidCoordinates();

        // Apply filters
        if ($request->filled('location')) {
            $query->byLocation($request->location);
        }

        if ($request->filled('min_price')) {
            $query->byMinPrice($request->min_price);
        }
        
        if ($request->filled('max_price')) {
            $query->byMaxPrice($request->max_price);
        }

        if ($request->filled('property_type')) {
            $query->where('property_type', 'like', "%{$request->property_type}%");
        }

        if ($request->filled('available_date')) {
            $query->where('available_date', 'like', "%{$request->available_date}%");
        }

        if ($request->filled('management_company')) {
            $query->byManagementCompany($request->management_company);
        }

        if ($request->filled('agent_name') && $canUseRestrictedFilters) {
            $query->where('agent_name', 'like', "%{$request->agent_name}%");
        }

        if ($request->filled('paying_only') && $canUseRestrictedFilters) {
            $query->whereRaw("LOWER(TRIM(COALESCE(paying, ''))) = 'yes'");
        }

        if ($request->filled('couples_allowed')) {
            $query->byCouplesAllowed($request->couples_allowed);
        }

        if ($request->filled('ensuite')) {
            $query->byEnsuite($request->ensuite);
        }

        if ($request->filled('room_count')) {
            $query->where('total_rooms', $request->room_count);
        }

        // Get properties with all necessary fields for map display
        // Sort by premium flag first, then by latest
        $properties = $query
            ->orderByRaw("CASE WHEN LOWER(flag) = 'premium' THEN 0 ELSE 1 END")
            ->latest()
            ->limit(400)
            ->get();

        // Get filter values for dropdowns
        $locations = Property::distinct()->pluck('location')->filter()->sort()->values();
        $propertyTypes = Property::distinct()->pluck('property_type')->sort()->values();
        $availableDates = Property::distinct()->pluck('available_date')->sort()->values();
        $roomCounts = Property::distinct()->pluck('total_rooms')->filter()->sort()->values();
        
        if (auth()->check()) {
            $agentNames = Property::distinct()->pluck('agent_name')->filter()->sort()->values();
            $agentsWithPaying = Property::whereNotNull('agent_name')
                ->where('agent_name', '!=', '')
                ->select('agent_name', 'paying')
                ->get()
                ->groupBy('agent_name')
                ->map(function ($properties) {
                    return $properties->contains(function ($property) {
                        return strtolower($property->paying ?? '') === 'yes';
                    });
                });
        } else {
            $agentNames = collect();
            $agentsWithPaying = collect();
        }

        // Convert to array for JSON encoding
        $propertiesForJson = $properties->map(function ($property) {
            return [
                'id' => $property->id,
                'title' => $property->title,
                'location' => $property->location,
                'latitude' => $property->latitude ? (float) $property->latitude : null,
                'longitude' => $property->longitude ? (float) $property->longitude : null,
                'price' => $property->price,
                'property_type' => $property->property_type,
                'agent_name' => $property->agent_name,
                'management_company' => $property->management_company,
                'couples_ok' => $property->couples_ok ?? '',
                'couples_allowed' => $property->couples_allowed ?? '',
                'description' => $property->description ?? '',
                'first_photo_url' => $property->first_photo_url,
                'high_quality_photos_array' => $property->high_quality_photos_array,
                'total_rooms' => $property->total_rooms ?? '',
                'room_count' => $property->total_rooms ?? '',
            ];
        })->values()->all();

        \Log::info('Map query results (Database Fallback)', [
            'total_properties' => $properties->count(),
            'with_coords' => $properties->filter(function($p) {
                return !empty($p->latitude) && !empty($p->longitude);
            })->count(),
        ]);

        return view('properties.map', [
            'properties' => $properties,
            'propertiesForJson' => $propertiesForJson,
            'locations' => $locations,
            'propertyTypes' => $propertyTypes,
            'availableDates' => $availableDates,
            'agentNames' => $agentNames,
            'agentsWithPaying' => $agentsWithPaying,
            'roomCounts' => $roomCounts
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * This is the public/create form for properties which will
     * create a local Property record and also append to Google Sheets
     * (if Google Sheets is configured).
     */
    public function create()
    {
        return view('properties.create');
    }

    /**
     * Store a newly created resource in storage and append to Google Sheets.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'property_type' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'amenities' => 'nullable|string',
            'bills_included' => 'nullable|string',
            'deposit' => 'nullable|string',
            'minimum_term' => 'nullable|string',
            'furnishings' => 'nullable|string',
            'garden_patio' => 'nullable|string',
            'contact_info' => 'nullable|string',
            'management_company' => 'nullable|string|max:255',
            'available_date' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'status' => 'nullable|in:available,rented,unavailable,on_hold',
        ]);
        
        // Create local Property record (existing behaviour)
        $propertyData = $request->all();
        $propertyData['updatable'] = false; // CRM-created properties should not be updated by imports
        $property = Property::create($propertyData);

        // Also append to Google Sheets "Properties" worksheet if configured
        $sheetsService = $this->getSheetsService();
        if ($sheetsService) {
            try {
                $sheetsService->appendProperty($property->toArray());
            } catch (\Exception $e) {
                \Log::error('Failed to append property to Google Sheets from PropertyController@store', [
                    'property_id' => $property->id ?? null,
                    'error' => $e->getMessage(),
                ]);
                // Do not block the user flow if Sheets fails
            }
        }

        return redirect()->route('properties.show', $property)
            ->with('success', 'Property created successfully!');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $property = Property::findOrFail($id);
        return view('properties.edit', compact('property'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $property = Property::findOrFail($id);
        
        $request->validate([
            'title' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'property_type' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'amenities' => 'nullable|string',
            'bills_included' => 'nullable|string',
            'deposit' => 'nullable|string',
            'minimum_term' => 'nullable|string',
            'furnishings' => 'nullable|string',
            'garden_patio' => 'nullable|string',
            'contact_info' => 'nullable|string',
            'management_company' => 'nullable|string|max:255',
            'available_date' => 'nullable|string',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'status' => 'nullable|in:available,rented,unavailable,on_hold',
        ]);
        
        $property->update($request->all());
        
        return redirect()->route('properties.show', $property)
            ->with('success', 'Property updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $property = Property::findOrFail($id);
        $property->delete();
        
        return redirect()->route('properties.index')
            ->with('success', 'Property deleted successfully!');
    }
}
