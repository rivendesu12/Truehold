<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\User;
use App\Models\Client;
use App\Models\PropertyInterest;
use App\Services\PropertyGoogleSheetsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    protected $sheetsService;

    public function __construct(PropertyGoogleSheetsService $sheetsService)
    {
        $this->sheetsService = $sheetsService;
    }
    
    public function dashboard()
    {
        $totalProperties = Property::count();
        $propertiesWithCoords = Property::withValidCoordinates()->count();
        $recentProperties = Property::latest()->take(5)->get();
        
        return view('admin.dashboard', compact('totalProperties', 'propertiesWithCoords', 'recentProperties'));
    }

    public function properties(Request $request)
    {
        $query = Property::query();

        // Apply search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  ->orWhere('location', 'like', "%{$searchTerm}%")
                  ->orWhere('management_company', 'like', "%{$searchTerm}%")
                  ->orWhere('agent_name', 'like', "%{$searchTerm}%");
            });
        }

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('location')) {
            $query->byLocation($request->location);
        }

        if ($request->filled('property_type')) {
            $query->where('property_type', 'like', "%{$request->property_type}%");
        }

        // Price filtering - handle min and max separately
        if ($request->filled('min_price')) {
            $query->byMinPrice($request->min_price);
        }
        
        if ($request->filled('max_price')) {
            $query->byMaxPrice($request->max_price);
        }

        if ($request->filled('available_date')) {
            $query->where('available_date', 'like', "%{$request->available_date}%");
        }

        // Management Company and Agent Name filters
        if ($request->filled('management_company')) {
            $query->byManagementCompany($request->management_company);
        }

        if ($request->filled('agent_name')) {
            $query->where('agent_name', 'like', "%{$request->agent_name}%");
        }

        if ($request->filled('london_area')) {
            $query->byLondonArea($request->london_area);
        }

        if ($request->filled('couples_allowed')) {
            $query->byCouplesAllowed($request->couples_allowed);
        }

        // Get unique values for filter dropdowns
        $locations = Property::distinct()->pluck('location')->filter()->sort()->values();
        $propertyTypes = Property::distinct()->pluck('property_type')->filter()->sort()->values();
        $availableDates = Property::distinct()->pluck('available_date')->filter()->sort()->values();
        $agentNames = Property::distinct()->pluck('agent_name')->filter()->sort()->values();
        $managementCompanies = Property::distinct()->pluck('management_company')->filter()->sort()->values();

        $properties = $query->latest()->paginate(20)->withQueryString();

        return view('admin.properties.index', compact('properties', 'locations', 'propertyTypes', 'availableDates', 'agentNames', 'managementCompanies'));
    }

    public function create()
    {
        return view('admin.properties.create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'price' => 'required|numeric|min:0',
            'description' => 'required|string',
            'property_type' => 'required|string|max:100',
            'available_date' => 'nullable|string|max:100',
            'status' => 'required|string|in:available,rented,unavailable',
            'amenities' => 'nullable|string',
            'photos' => 'required|array|min:1',
            'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Handle image uploads
        $photos = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $image) {
                $filename = time() . '_' . uniqid() . '_' . $image->getClientOriginalName();
                $path = Storage::disk('public')->putFileAs('images/properties', $image, $filename);
                $photos[] = \App\Helpers\StorageHelper::getStorageUrl($path);
            }
        }

        // Create property data
        $propertyData = $request->except(['photos']);
        $propertyData['photos'] = $photos; // Store as array (Laravel will handle JSON conversion)
        $propertyData['all_photos'] = implode(',', $photos); // Store as comma-separated string for compatibility
        $propertyData['first_photo_url'] = $photos[0] ?? null;
        $propertyData['photo_count'] = count($photos);
        $propertyData['updatable'] = false; // CRM-created properties should not be updated by imports

        $property = Property::create($propertyData);

        return redirect()->route('admin.properties')
            ->with('success', 'Property created successfully with ' . count($photos) . ' images!');
    }

    // User Management Methods
    public function users()
    {
        $users = User::latest()->paginate(20);
        return view('admin.users.index', compact('users'));
    }

    public function createUser()
    {
        return view('admin.users.create');
    }

    public function storeUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|in:agent,marketing_agent,admin',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'roles' => $request->roles,
            'role' => $request->roles[0] ?? 'user', // Set primary role for backward compatibility
        ]);

        return redirect()->route('admin.users')
            ->with('success', 'User created successfully!');
    }

    public function editUser(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function updateUser(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'roles' => 'nullable|array',
            'roles.*' => 'string|in:agent,marketing_agent,admin',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        // Handle roles
        if ($request->has('roles')) {
            $userData['roles'] = $request->roles;
        }

        $user->update($userData);

        return redirect()->route('admin.users')
            ->with('success', 'User updated successfully!');
    }

    public function destroyUser(User $user)
    {
        // Prevent deleting the current user
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account!');
        }

        $user->delete();

        return redirect()->route('admin.users')
            ->with('success', 'Agent user deleted successfully!');
    }

    public function edit(Property $property)
    {
        $property->load(['interests.client', 'interestedClients']);
        $clients = Client::orderBy('full_name')->get();
        return view('admin.properties.edit', compact('property', 'clients'));
    }

    public function update(Request $request, Property $property)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'property_type' => 'nullable|string|max:100',
            'available_date' => 'nullable|string|max:100',
            'status' => 'required|string|in:available,rented,unavailable',
            'management_company' => 'nullable|string|max:255',
            'amenities' => 'nullable|string',
            'bedrooms' => 'nullable|integer|min:0|max:20',
            'bathrooms' => 'nullable|numeric|min:0|max:20',
            'min_term' => 'nullable|string|max:100',
            'max_term' => 'nullable|string|max:100',
            'deposit' => 'nullable|string|max:100',
            'bills_included' => 'nullable|string|in:yes,no,some',
            'parking' => 'nullable|string|in:yes,no,street',
            'housemates' => 'nullable|integer|min:0|max:20',
            'agent_name' => 'nullable|string|max:255',
            'couples_ok' => 'nullable|string|in:yes,no',
            'link' => 'nullable|url|max:500',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'removed_images' => 'nullable|string',
            'flag' => 'nullable|string|max:50',
            'flag_color' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Get current photos
        $currentPhotos = [];
        if ($property->photos) {
            $currentPhotos = is_array($property->photos) ? $property->photos : [];
        }

        // Handle removed images
        if ($request->filled('removed_images')) {
            $removedIndices = json_decode($request->input('removed_images'), true) ?: [];
            // Remove images from the end to avoid index shifting issues
            rsort($removedIndices);
            foreach ($removedIndices as $index) {
                if (isset($currentPhotos[$index])) {
                    unset($currentPhotos[$index]);
                }
            }
            // Re-index array
            $currentPhotos = array_values($currentPhotos);
        }

        // Handle new image uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $filename = time() . '_' . uniqid() . '_' . $image->getClientOriginalName();
                $path = Storage::disk('public')->putFileAs('images/properties', $image, $filename);
                $currentPhotos[] = \App\Helpers\StorageHelper::getStorageUrl($path);
            }
        }

        // Handle first photo selection
        if ($request->has('first_photo') && !empty($currentPhotos)) {
            $firstPhotoIndex = (int) $request->input('first_photo');
            if (isset($currentPhotos[$firstPhotoIndex])) {
                // Move selected photo to first position
                $selectedPhoto = $currentPhotos[$firstPhotoIndex];
                unset($currentPhotos[$firstPhotoIndex]);
                array_unshift($currentPhotos, $selectedPhoto);
            }
        }

        // Update property data
        $propertyData = $request->except(['images', 'first_photo', 'removed_images']);
        $propertyData['photos'] = $currentPhotos; // Store as array (Laravel will handle JSON conversion)
        $propertyData['all_photos'] = implode(',', $currentPhotos); // Store as comma-separated string for compatibility
        $propertyData['first_photo_url'] = $currentPhotos[0] ?? null;
        $propertyData['photo_count'] = count($currentPhotos);

        $property->update($propertyData);

        return redirect()->route('admin.properties')
            ->with('success', 'Property updated successfully!');
    }

    /**
     * Attach a registered client as interested in a property.
     */
    public function addInterestedClient(Request $request, Property $property)
    {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $interest = PropertyInterest::firstOrCreate(
            [
                'property_id' => $property->id,
                'client_id' => $data['client_id'],
            ],
            [
                'added_by_user_id' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]
        );

        if (!$interest->wasRecentlyCreated) {
            // Update notes and added_by if re-attaching
            $interest->update([
                'notes' => $data['notes'] ?? $interest->notes,
                'added_by_user_id' => $interest->added_by_user_id ?: auth()->id(),
            ]);
        }

        return back()->with('success', 'Client added as interested in this property.');
    }

    /**
     * Detach an interested client from a property.
     */
    public function removeInterestedClient(Property $property, Client $client)
    {
        PropertyInterest::where('property_id', $property->id)
            ->where('client_id', $client->id)
            ->delete();

        return back()->with('success', 'Client removed from interested list.');
    }

    public function destroy(Property $property)
    {
        $property->delete();
        return redirect()->route('admin.properties')
            ->with('success', 'Property deleted successfully!');
    }

    public function uploadImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
            
            // Store in public/images/properties
            $path = Storage::disk('public')->putFileAs('images/properties', $file, $filename);
            
            $url = \App\Helpers\StorageHelper::getStorageUrl($path);
            
            return response()->json([
                'success' => true,
                'url' => $url,
                'filename' => $filename
            ]);
        }

        return response()->json(['error' => 'No image uploaded'], 400);
    }

    // Client Management Methods
    public function clients()
    {
        $clients = \App\Models\Client::with('agent')->latest()->paginate(20);
        $agents = \App\Models\Agent::all();
        $nationalities = \App\Models\Client::distinct()->pluck('nationality')->filter()->sort()->values();
        
        return view('admin.clients.index', compact('clients', 'agents', 'nationalities'));
    }

    public function createClient()
    {
        $agentUsers = \App\Models\User::whereHas('agent')->orWhere('role', 'agent')->orWhereJsonContains('roles', 'agent')->with('agent')->get();
        $marketingUsers = \App\Models\User::where('role', 'marketing_agent')->orWhereJsonContains('roles', 'marketing_agent')->get();
        return view('admin.clients.create', compact('agentUsers', 'marketingUsers'));
    }

    public function storeClient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today',
            'phone_number' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'nationality' => 'required|string|max:100',
            'current_address' => 'required|string',
            'company_university_name' => 'required|string|max:255',
            'company_university_address' => 'required|string',
            'position_role' => 'required|string|max:255',
            'budget' => 'required|numeric|min:0',
            'area_of_interest' => 'required|string|max:255',
            'moving_date' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string',
            'current_landlord_name' => 'required|string|max:255',
            'current_landlord_contact_info' => 'required|string',
            'registration_status' => 'required|in:registered,unregistered',
            'agent_user_id' => 'required|exists:users,id',
            'marketing_agent_id' => 'nullable|exists:users,id',
        ], [
            'full_name.required' => 'Full name is required.',
            'date_of_birth.required' => 'Date of birth is required.',
            'phone_number.required' => 'Phone number is required.',
            'email.required' => 'Email is required.',
            'nationality.required' => 'Nationality is required.',
            'current_address.required' => 'Current address is required.',
            'company_university_name.required' => 'Company/University name is required.',
            'company_university_address.required' => 'Company/University address is required.',
            'position_role.required' => 'Position/Role is required.',
            'budget.required' => 'Budget is required.',
            'area_of_interest.required' => 'Area of interest is required.',
            'moving_date.required' => 'Moving date is required.',
            'current_landlord_name.required' => 'Current landlord name is required.',
            'current_landlord_contact_info.required' => 'Current landlord contact info is required.',
            'registration_status.required' => 'Registration status is required.',
            'agent_user_id.required' => 'Assigned agent is required.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $request->all();
        
        // Always assign current user as agent (session-based)
        $currentUser = auth()->user();
        if ($currentUser) {
            if ($currentUser->agent) {
                $data['agent_id'] = $currentUser->agent->id;
                \Log::info("Using existing agent profile. Agent ID: " . $currentUser->agent->id);
            } else {
                // Create agent profile for current user
                $agent = \App\Models\Agent::firstOrCreate(
                    ['user_id' => $currentUser->id],
                    [
                        'company_name' => $currentUser->name,
                        'is_verified' => false,
                        'is_featured' => false,
                    ]
                );
                $data['agent_id'] = $agent->id;
                \Log::info("Created new agent profile. Agent ID: " . $agent->id);
            }
        } else {
            \Log::warning("No authenticated user found for agent assignment");
        }
        
        \Log::info("Final agent_id being assigned: " . ($data['agent_id'] ?? 'NULL'));
        
        // Ensure agent_id is set - if not, create a default agent profile
        if (empty($data['agent_id'])) {
            $currentUser = auth()->user();
            if ($currentUser) {
                $agent = \App\Models\Agent::firstOrCreate(
                    ['user_id' => $currentUser->id],
                    [
                        'company_name' => $currentUser->name,
                        'is_verified' => false,
                        'is_featured' => false,
                    ]
                );
                $data['agent_id'] = $agent->id;
                \Log::info("Fallback: Created agent profile. Agent ID: " . $agent->id);
            }
        }
        
        // Remove agent_user_id as we're using session-based assignment
        unset($data['agent_user_id']);

        \App\Models\Client::create($data);

        return redirect()->route('admin.clients')
            ->with('success', 'Client created successfully!');
    }

    public function editClient(\App\Models\Client $client)
    {
        $agentUsers = \App\Models\User::whereHas('agent')->orWhere('role', 'agent')->orWhereJsonContains('roles', 'agent')->with('agent')->get();
        $marketingUsers = \App\Models\User::where('role', 'marketing_agent')->orWhereJsonContains('roles', 'marketing_agent')->get();
        return view('admin.clients.edit', compact('client', 'agentUsers', 'marketingUsers'));
    }

    public function updateClient(Request $request, \App\Models\Client $client)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'nullable|date|before:today',
            'phone_number' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'nationality' => 'nullable|string|max:100',
            'current_address' => 'nullable|string',
            'company_university_name' => 'nullable|string|max:255',
            'company_university_address' => 'nullable|string',
            'position_role' => 'nullable|string|max:255',
            'budget' => 'nullable|numeric|min:0',
            'area_of_interest' => 'nullable|string|max:255',
            'moving_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string',
            'current_landlord_name' => 'nullable|string|max:255',
            'current_landlord_contact_info' => 'nullable|string',
            'registration_status' => 'nullable|in:registered,unregistered',
            'agent_user_id' => 'nullable|exists:users,id',
            'marketing_agent_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $request->all();
        if (!empty($data['agent_user_id'])) {
            $agentId = \App\Models\Agent::where('user_id', $data['agent_user_id'])->value('id');
            $data['agent_id'] = $agentId;
        } else {
            // allow clearing assigned agent
            if (array_key_exists('agent_user_id', $data)) {
                $data['agent_id'] = null;
            }
        }
        unset($data['agent_user_id']);

        $client->update($data);

        return redirect()->route('admin.clients')
            ->with('success', 'Client updated successfully!');
    }

    public function destroyClient(\App\Models\Client $client)
    {
        $client->delete();
        return redirect()->route('admin.clients')
            ->with('success', 'Client deleted successfully!');
    }

    public function toggleRegistrationStatus(Request $request, \App\Models\Client $client)
    {
        $validated = $request->validate([
            'registration_status' => 'required|in:registered,unregistered',
        ]);

        $client->update(['registration_status' => $validated['registration_status']]);

        return response()->json([
            'success' => true,
            'message' => 'Registration status updated successfully',
            'new_status' => $client->registration_status,
        ]);
    }

    /**
     * Toggle the updatable status of a property
     */
    public function toggleUpdatable(Property $property)
    {
        $property->update(['updatable' => !$property->updatable]);
        
        return redirect()->back()->with('success', 
            'Property updatable status changed to ' . ($property->updatable ? 'enabled' : 'disabled')
        );
    }
    
    /**
     * Display the property flags management page
     */
    public function flags(Request $request)
    {
        // If not already reloaded, force cache clear and reload
        if (!$request->has('_reloaded')) {
            $this->sheetsService->clearCache();
            \Log::info('Properties cache auto-cleared, redirecting to refresh page');
            
            // Redirect to same URL with _reloaded parameter to prevent loop
            $params = array_merge($request->query(), ['_reloaded' => '1']);
            return redirect()->route('admin.properties.flags', $params);
        }
        
        // Force clear cache if requested
        if ($request->has('clear_cache')) {
            $this->sheetsService->clearCache();
            \Log::info('Properties cache manually cleared via flags page');
        }
        
        // Get all properties from Google Sheets
        $allProperties = $this->sheetsService->getAllProperties();
        
        // Apply search filter
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $allProperties = $allProperties->filter(function($property) use ($search) {
                $title = strtolower($property['title'] ?? '');
                $location = strtolower($property['location'] ?? '');
                return str_contains($title, $search) || str_contains($location, $search);
            });
        }
        
        // Filter by flag status
        if ($request->filled('filter')) {
            if ($request->filter === 'with_flag') {
                $allProperties = $allProperties->filter(function($property) {
                    return !empty($property['flag']);
                });
            } elseif ($request->filter === 'no_flag') {
                $allProperties = $allProperties->filter(function($property) {
                    return empty($property['flag']);
                });
            }
        }
        
        // Filter by agent
        if ($request->filled('agent')) {
            $agentFilter = $request->agent;
            $allProperties = $allProperties->filter(function($property) use ($agentFilter) {
                return ($property['agent_name'] ?? '') === $agentFilter;
            });
        }
        
        // Pagination
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        $paginatedProperties = $allProperties->slice($offset, $perPage)->values();
        
        $properties = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedProperties,
            $allProperties->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        
        // Get distinct agent names for filter dropdown
        $agentNames = $this->sheetsService->getAllProperties()
            ->pluck('agent_name')
            ->filter(function($name) {
                return !empty($name) && $name !== 'N/A';
            })
            ->unique()
            ->sort()
            ->values();
        
        return view('admin.properties.flags', compact('properties', 'agentNames'));
    }
    
    /**
     * Update property flag via AJAX
     */
    public function updateFlag(Request $request, $propertyId)
    {
        $request->validate([
            'flag' => 'nullable|string|max:50',
            'flag_color' => 'nullable|string|max:100',
        ]);
        
        \Log::info('Flag update request received', [
            'property_id' => $propertyId,
            'flag' => $request->flag,
            'flag_color' => $request->flag_color,
            'is_ajax' => $request->ajax()
        ]);
        
        // Update in Google Sheets
        $success = $this->sheetsService->updateProperty($propertyId, [
            'flag' => $request->flag ?? '',
            'flag_color' => $request->flag_color ?? '',
        ]);
        
        if ($request->ajax()) {
            $message = $success 
                ? 'Flag updated successfully!' 
                : 'Failed to update flag. Check if property ID exists in sheet and columns are present.';
            
            \Log::info('Flag update AJAX response', [
                'property_id' => $propertyId,
                'success' => $success,
                'message' => $message
            ]);
            
            return response()->json([
                'success' => $success,
                'message' => $message,
                'property_id' => $propertyId
            ]);
        }
        
        if ($success) {
            return back()->with('success', 'Property flag updated successfully!');
        }
        
        return back()->with('error', 'Failed to update property flag. Please check the logs for details.');
    }
}
