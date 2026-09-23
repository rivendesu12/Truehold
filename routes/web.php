<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\RentalCodeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\GroupViewingController;
use App\Http\Controllers\CallLogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserPermissionController;
use App\Http\Controllers\ScraperController;
use App\Http\Controllers\PhpScraperController;
use App\Http\Controllers\RentalCodeCashDocumentController;
use App\Http\Controllers\AgentProfileController;
use App\Http\Controllers\PublicClientController;
use App\Http\Controllers\ApPropertyController;
use App\Http\Controllers\ApPublicPropertyController;
use App\Http\Controllers\TwilioWebhookController;
use App\Http\Controllers\PropertyManagementController;
use Twilio\Rest\Client;



Route::post('/twilio/webhook', [TwilioWebhookController::class, 'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
// Public AP Properties on subdomain ap.truehold.co.uk - define before generic routes
Route::domain('ap.truehold.co.uk')->group(function () {
    Route::get('/', [ApPublicPropertyController::class, 'index'])->name('ap.public.index');
    Route::get('/properties', [ApPublicPropertyController::class, 'index'])->name('ap.public.list');
    Route::get('/properties/{ap_property}', [ApPublicPropertyController::class, 'show'])->name('ap.public.show');
});

// Listings index now lives at the domain root ("/") instead of "/properties".
Route::get('/', function (Request $request, PropertyController $controller) {
    if ($request->getHost() === 'ap.truehold.co.uk') {
        return redirect()->route('ap.public.index');
    }
    return $controller->index($request);
})->name('properties.index');


// Public routes - no authentication required
// Legacy "/properties" URL — permanently redirect to root, preserving any query string.
Route::get('/properties', function (Request $request) {
    return redirect('/' . ($request->getQueryString() ? '?' . $request->getQueryString() : ''), 301);
});
Route::post('/properties/share-token', [PropertyController::class, 'createShareToken'])->name('properties.share-token');
Route::get('/properties/create', [PropertyController::class, 'create'])->name('properties.create');
Route::post('/properties', [PropertyController::class, 'store'])->name('properties.store');
Route::get('/properties/map', [PropertyController::class, 'map'])->name('properties.map');
Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
Route::get('/manage/properties', [PropertyManagementController::class, 'index'])->name('properties.manage');

// Agent search assistant: plain-English search, signed-in agents only.
Route::middleware(['auth', 'throttle:60,1', \App\Http\Middleware\LogSigouInteraction::class])->post('/agent-search', function (Request $request) {
    // Every message is a paid model call: a sentence is plenty, and a pasted
    // essay should not be billed as one.
    $question = mb_substr(trim((string) $request->input('q', '')), 0, 600);
    if ($question === '') {
        return response()->json(['error' => 'Ask me something, malaka.'], 422);
    }

    $assistant = app(\App\Services\AgentSearchAssistant::class);
    if (! $assistant->isConfigured()) {
        return response()->json([
            'error' => 'The assistant is not configured yet — ANTHROPIC_API_KEY is missing.',
        ], 503);
    }

    $feed = app(\App\Services\ScrapedListingsApiService::class)->getAllProperties();
    $locations = $feed->pluck('location')->filter()->unique()->values()->all();

    // The agent's last search, so "max 650" refines it instead of starting
    // over. Kept 15 minutes, per session; "New search" in the panel drops it.
    $previous = null;
    if (! $request->boolean('fresh')) {
        $last = $request->session()->get('sigou.last');
        if (is_array($last) && ($last['at'] ?? 0) > now()->subMinutes(15)->timestamp) {
            $previous = $last['filters'] ?? null;
        }
    }

    // An agreement Sigou is still collecting details for ("full name as on
    // the ID?"), so the agent's answer completes it.
    $pending = null;
    $lastAgreement = $request->session()->get('sigou.agreement');
    if (! $request->boolean('fresh') && is_array($lastAgreement) && ($lastAgreement['at'] ?? 0) > now()->subMinutes(15)->timestamp) {
        $pending = $lastAgreement['details'] ?? null;
    }

    $pendingInvoice = null;
    $lastInvoice = $request->session()->get('sigou.invoice');
    if (! $request->boolean('fresh') && is_array($lastInvoice) && ($lastInvoice['at'] ?? 0) > now()->subMinutes(15)->timestamp) {
        $pendingInvoice = $lastInvoice['details'] ?? null;
    }

    $spec = $assistant->parse($question, $locations, $previous ?: null, $pending ?: null, $pendingInvoice ?: null);
    // For the interaction log (LogSigouInteraction).
    $request->attributes->set('sigou.spec', $spec);
    $request->attributes->set('sigou.usage', $assistant->lastUsage);
    if (! $spec) {
        return response()->json(['error' => 'Could not understand that — try rephrasing.'], 502);
    }

    // The office WiFi. The model only spots the question; the details come
    // from config and never go to the model.
    if (! empty($spec['wifi'])) {
        $wifi = config('services.office_wifi');

        return response()->json([
            'wifi' => ! empty($wifi['ssid']) ? [
                'ssid' => $wifi['ssid'],
                'password' => $wifi['password'],
                'qr_url' => $wifi['qr_url'],
                // The client scans the agent's screen and is on: no typing.
                'qr' => (new \BaconQrCode\Writer(new \BaconQrCode\Renderer\ImageRenderer(
                    new \BaconQrCode\Renderer\RendererStyle\RendererStyle(260, 1),
                    new \BaconQrCode\Renderer\Image\SvgImageBackEnd(),
                )))->writeString('WIFI:T:WPA;S:' . addcslashes($wifi['ssid'], '\\;,:"')
                    . ';P:' . addcslashes((string) $wifi['password'], '\\;,:"') . ';;'),
            ] : null,
            'sigou' => ! empty($wifi['ssid']) ? (string) ($spec['sigou'] ?? '') : 'Nobody told me the WiFi yet, ask Giaco',
            'groups' => ['commission' => [], 'standard' => [], 'alternatives' => []],
        ]);
    }

    // A partner agency: their vacancy link, their terms, or their rooms.
    $ask = (array) ($spec['agency_request'] ?? []);
    if (! empty($ask['wanted']) && ! empty($ask['name'])) {
        $directory = app(\App\Services\AgencyDirectory::class);
        $agency = $directory->find($ask['name']);

        if (($ask['want'] ?? null) === 'bank') {
            $bank = app(\App\Services\AgencyBankDetails::class)->find($ask['name'])
                ?? ($agency ? app(\App\Services\AgencyBankDetails::class)->find($agency['name']) : null);

            return response()->json([
                'agency' => null,
                'agency_bank' => $bank,
                'agency_asked' => $ask['name'],
                'sigou' => $bank ? (string) ($spec['sigou'] ?? '') : 'No bank details for ' . ($agency['name'] ?? $ask['name']) . ' yet malaka, ask Giaco to send them',
                'groups' => ['commission' => [], 'standard' => [], 'alternatives' => []],
            ]);
        }

        if (($ask['want'] ?? null) === 'listings') {
            // Their rooms: an ordinary search restricted to them.
            $spec['agencies'] = array_values(array_unique(array_merge((array) ($spec['agencies'] ?? []), [$agency['name'] ?? $ask['name']])));
        } else {
            $key = \App\Services\AgencyDirectory::key($agency['name'] ?? $ask['name']);
            $rooms = $feed->filter(fn ($p) => ($k = \App\Services\AgencyDirectory::key((string) ($p['agent_name'] ?? $p['landlord_name'] ?? ''))) !== ''
                && ($k === $key || preg_match('/(^| )' . preg_quote($key, '/') . '( |$)/', $k)))->count();

            return response()->json([
                'agency' => $agency ? $agency + ['rooms' => $rooms, 'want' => $ask['want'] ?? 'link'] : null,
                'agency_asked' => $ask['name'],
                'sigou' => $agency ? (string) ($spec['sigou'] ?? '') : 'Who is ' . $ask['name'] . '? Not in our agencies list bro',
                'groups' => ['commission' => [], 'standard' => [], 'alternatives' => []],
            ]);
        }
    }

    // A sourcing-fee invoice. When an agreement is asked for in the same
    // breath, the agreement card offers the invoice too.
    $bill = (array) ($spec['invoice'] ?? []);
    if (! empty($bill['wanted']) && empty($spec['agreement']['wanted'])) {
        $request->session()->forget('sigou.invoice');
        $given = fn (string $key) => ($bill[$key] ?? null) !== null && $bill[$key] !== '' ? $bill[$key] : ($pendingInvoice[$key] ?? null);
        try {
            $date = $given('date') ? \Carbon\Carbon::parse($given('date'))->toDateString() : now()->toDateString();
        } catch (\Throwable $e) {
            $date = now()->toDateString();
        }
        $details = [
            'client_name' => $given('client_name') ? trim((string) $given('client_name')) : null,
            'amount' => is_numeric($given('amount')) ? (float) $given('amount') : null,
            'date' => $date,
            'paid' => (bool) ($bill['paid'] ?? true),
        ];
        $missing = array_keys(array_filter(['client_name' => ! $details['client_name'], 'amount' => $details['amount'] === null]));
        if ($missing) {
            $request->session()->put('sigou.invoice', ['details' => $details, 'at' => now()->timestamp]);
        }

        return response()->json([
            'invoice' => $details + [
                'missing' => $missing,
                'ready' => ! $missing,
                'pdf_url' => route('invoice.pdf'),
                'next_number' => app(\App\Services\SourcingInvoice::class)->nextNumber(),
            ],
            'sigou' => (string) ($spec['sigou'] ?? ''),
            'groups' => ['commission' => [], 'standard' => [], 'alternatives' => []],
        ]);
    }

    // A sourcing agreement, not a search.
    $wants = (array) ($spec['agreement'] ?? []);
    if (! empty($wants['wanted'])) {
        $request->session()->forget('sigou.agreement');

        if (! empty($wants['template_only'])) {
            return response()->json([
                'agreement' => ['template' => true, 'template_url' => route('agreement.template')],
                'sigou' => (string) ($spec['sigou'] ?? ''),
                'groups' => ['commission' => [], 'standard' => [], 'alternatives' => []],
            ]);
        }

        $given = fn (string $key) => ($wants[$key] ?? null) !== null && $wants[$key] !== '' ? $wants[$key] : ($pending[$key] ?? null);
        $date = $given('date');
        try {
            $date = $date ? \Carbon\Carbon::parse($date)->toDateString() : now()->toDateString();
        } catch (\Throwable $e) {
            $date = now()->toDateString();
        }

        $details = [
            'client_name' => $given('client_name') ? trim((string) $given('client_name')) : null,
            'fee' => is_numeric($given('fee')) ? (float) $given('fee') : null,
            'date' => $date,
            // Never the login: agents share one ("Agent"). Asked once, then
            // remembered on that device from the last agreement they signed.
            'sign_as' => \App\Support\AgentName::canonical($given('sign_as')) ?: $request->session()->get('sigou.signer'),
        ];
        $missing = array_keys(array_filter([
            'client_name' => ! $details['client_name'],
            'fee' => $details['fee'] === null,
            'sign_as' => ! $details['sign_as'],
        ]));

        if ($missing) {
            $request->session()->put('sigou.agreement', ['details' => $details, 'at' => now()->timestamp]);
        }
        // The model cannot know who is at the keyboard, so when that is all
        // that is missing it must not say "ready".
        if ($missing === ['sign_as']) {
            $spec['sigou'] = 'All good, just who signs? Put your name bro';
        }

        return response()->json([
            'agreement' => $details + [
                'referral' => \App\Services\SourcingAgreement::REFERRAL_BONUS,
                'missing' => $missing,
                'ready' => ! $missing,
                'pdf_url' => route('agreement.pdf'),
                'invoice_url' => route('invoice.pdf'),
                'template_url' => route('agreement.template'),
            ],
            'sigou' => (string) ($spec['sigou'] ?? ''),
            'groups' => ['commission' => [], 'standard' => [], 'alternatives' => []],
        ]);
    }

    // Moved on to something else: half-made documents are dropped.
    if ($pending) {
        $request->session()->forget('sigou.agreement');
    }
    if ($pendingInvoice) {
        $request->session()->forget('sigou.invoice');
    }

    // Just talking to Sigou, not searching: he answers and nothing is filtered,
    // and the search being refined is left as it was.
    if (! empty($spec['chit_chat'])) {
        return response()->json([
            'chat' => true,
            'sigou' => (string) ($spec['sigou'] ?? ''),
            'groups' => ['commission' => [], 'standard' => [], 'alternatives' => []],
        ]);
    }

    $request->session()->put('sigou.last', [
        'filters' => \App\Services\AgentSearchAssistant::carriedFilters($spec),
        'at' => now()->timestamp,
    ]);

    // "Who lives in Netherby House": only that property's rooms, shown with
    // their flatmates.
    $lookup = trim((string) ($spec['property_lookup'] ?? ''));
    if ($lookup !== '') {
        $feed = $feed->filter(fn ($p) => \App\Support\Household::isProperty($p, $lookup))->values();
    }

    $found = $assistant->search($spec, $feed);

    // Wildcards: the same brief over SpareRoom agents' free-to-contact rooms.
    // Their best matches, or the nearest ones when none match exactly; never
    // mixed into our own results.
    $wild = collect();
    $marketRooms = app(\App\Services\MarketListingsService::class)->all();
    if ($lookup !== '') {
        $marketRooms = $marketRooms->filter(fn ($p) => \App\Support\Household::isProperty($p, $lookup))->values();
    }
    // Wildcards are other agencies: none when the agent asked for particular ones.
    if ($marketRooms->isNotEmpty() && ! \App\Services\AgentSearchAssistant::splitAgencies($spec)[0] && empty($spec['commission_only']) && empty($found['unplaced'])) {
        $market = $assistant->search($spec, $marketRooms);
        $wild = $market['results']->take(6)->concat($market['alternatives']->take(max(0, 3 - $market['results']->count())))->values();
    }

    $hub = $found['hub'] ?? null;

    $shape = fn ($p) => [
        'id' => $p['id'] ?? null,
        'title' => $p['title'] ?? 'Untitled',
        'location' => $p['location'] ?? null,
        'price' => $p['price'] ?? null,
        'type' => \App\Support\PropertyClassifier::bucket($p),
        // Same order the property cards use: the high-quality set first, then
        // the single photo field. The panel was only reading the latter, so a
        // listing whose pictures live in `photos` showed an empty grey box.
        'photo' => \App\Support\PropertyPhoto::best($p),
        'agent' => $p['agent_name'] ?? null,
        'commission' => $assistant->paysCommission($p),
        // An estimated fee must read as an estimate: the rate behind it is a
        // default until the real agency terms are entered.
        'fee' => $p['commission_value'] ?? null,
        'fee_estimated' => (bool) ($p['commission_estimated'] ?? false),
        'zone' => $p['zone'] ?? null,
        'station' => $p['nearest_station'] ?? null,
        'lines' => array_slice((array) ($p['station_lines'] ?? []), 0, 3),
        'walk' => $p['walk_minutes'] ?? null,
        'beds' => $p['bedrooms'] ?? null,
        'house_size' => $p['house_size'] ?? null,
        'room_type' => $p['room_type'] ?? null,
        'journey' => $hub && isset($p['journey_minutes'][$hub])
            ? [
                'minutes' => $p['journey_minutes'][$hub]['minutes'],
                'changes' => $p['journey_minutes'][$hub]['changes'],
            ]
            : null,
        'why' => $p['why'] ?? null,
        'household' => \App\Support\Household::summary($p),
        // Wildcards open on our own page, never on SpareRoom.
        'url' => ! empty($p['market']) ? route('market.show', $p['token'])
            : (! empty($p['id']) ? url('/properties/' . $p['id']) : null),
        'market' => ! empty($p['market']),
    ];

    return response()->json([
        'model' => $assistant->provider() . '/' . $assistant->model(),
        'explanation' => $spec['explanation'] ?? '',
        'matched' => $found['matched'],
        'radius' => $found['radius'],
        'hub' => $found['hub_label'] ?? null,
        'unplaced' => $found['unplaced'] ?? null,
        'unanswerable' => $found['unanswerable'] ?? [],
        'why_none' => $found['why_none'] ?? [],
        'max_journey' => $found['max_journey'] ?? null,
        'widened' => (bool) ($found['widened'] ?? false),
        'area' => $found['area'] ?? null,
        'lookup' => $lookup !== '' ? $lookup : null,
        'relaxed' => $found['relaxed'] ?? [],
        'commission_only' => (bool) ($spec['commission_only'] ?? false),
        'sigou' => (string) ($spec['sigou'] ?? ''),
        'refined' => (bool) ($previous && ! empty($spec['refines_previous'])),
        'sigou_found' => (string) ($spec['sigou_found'] ?? ''),
        'sigou_none' => (string) ($spec['sigou_none'] ?? ''),
        // What the brief was understood as, so Sigou can comment on it.
        'brief' => array_intersect_key($spec, array_flip([
            'location', 'near_landmark', 'minutes_from_landmark', 'max_price', 'min_price',
            'ensuite_only', 'couples', 'pets', 'students', 'max_zone', 'region', 'room_type',
            'bills_included', 'no_deposit', 'parking', 'garden', 'sort', 'property_types',
        ])),
        'groups' => [
            'commission' => $found['commission']->take(24)->map($shape)->values(),
            'standard' => $found['standard']->take(24)->map($shape)->values(),
            'alternatives' => $found['alternatives']->map($shape)->values(),
            'wildcards' => $wild->map($shape)->values(),
        ],
    ]);
})->name('agent.search');

// Which result an agent opened, sent by the panel as a beacon. Our own
// database; used to see which section of Sigou's answer is actually useful.
Route::middleware('auth')->post('/agent-search/click', function (Request $request) {
    $data = $request->validate([
        'log' => ['required', 'integer'],
        'id' => ['required', 'string', 'max:80'],
        'band' => ['required', 'in:best,other,wild'],
        'pos' => ['nullable', 'integer', 'min:0', 'max:100'],
    ]);
    $row = \Illuminate\Support\Facades\DB::table('assistant_interactions')
        ->where('id', $data['log'])->where('user_id', $request->user()->id)->first();
    if ($row) {
        $clicks = json_decode((string) $row->clicks, true) ?: [];
        $clicks[] = ['id' => $data['id'], 'band' => $data['band'], 'pos' => $data['pos'] ?? null, 'at' => now()->toIso8601String()];
        \Illuminate\Support\Facades\DB::table('assistant_interactions')->where('id', $row->id)
            ->update(['clicks' => json_encode(array_slice($clicks, -50))]);
    }

    return response()->noContent();
})->name('agent.search.click');

// A wildcard (a SpareRoom agent's room) shown on our own page, never sent to
// SpareRoom. Clients opening a shared link get the room without the agency
// or the advert reference.
Route::get('/market/{token}', function (Request $request, string $token) {
    abort_unless(preg_match('/^[a-f0-9]{20}$/', $token), 404);
    $listing = app(\App\Services\MarketListingsService::class)->find($token);
    abort_unless($listing, 404);

    $isAgent = (bool) $request->user();
    if (! $isAgent) {
        // Contact details inside the advert text would let a client go round us.
        $listing['description'] = trim(preg_replace([
            '/(?:\+?44\s?|0)7\d{3}\s?\d{3}\s?\d{3}/', '/0\d{2,4}\s?\d{3,4}\s?\d{3,4}/',
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '#https?://\S+|www\.\S+#i',
        ], '[hidden]', (string) ($listing['description'] ?? '')));
        unset($listing['agency'], $listing['agent_name'], $listing['spareroom_id'], $listing['url'], $listing['link'], $listing['external_ref']);
    }

    return view('market.show', ['p' => $listing, 'isAgent' => $isAgent]);
})->name('market.show');

// The office's sourcing agreement, filled in and signed by the agent. POST so
// the client's name never sits in a URL, a log or the browser history.
Route::middleware('auth')->post('/tools/sourcing-agreement/pdf', function (Request $request) {
    $data = $request->validate([
        'client_name' => ['required', 'string', 'max:120'],
        'fee' => ['required', 'numeric', 'min:1', 'max:10000'],
        'date' => ['nullable', 'date'],
        'sign_as' => ['required', 'string', 'max:60'],
    ], ['sign_as.required' => 'Whose name goes on it? Fill in the agent signing as the Sourcer.']);

    $sourcer = \App\Support\AgentName::canonical($data['sign_as']);
    $request->session()->put('sigou.signer', $sourcer);
    $agreement = app(\App\Services\SourcingAgreement::class);

    return response($agreement->make(
        $data['client_name'],
        (float) $data['fee'],
        \Carbon\Carbon::parse($data['date'] ?? now()),
        $sourcer,
    ), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . \App\Services\SourcingAgreement::filename($data['client_name']) . '"',
        'Cache-Control' => 'private, no-store',
    ]);
})->name('agreement.pdf');

// The sourcing-fee invoice: numbered, kept in the admin's invoices table,
// returned as the PDF. Takes `amount`, or `fee` when it comes from the
// agreement card's "Invoice too".
Route::middleware('auth')->post('/tools/invoice/pdf', function (Request $request) {
    $request->merge(['amount' => $request->input('amount', $request->input('fee'))]);
    $data = $request->validate([
        'client_name' => ['required', 'string', 'max:120'],
        'amount' => ['required', 'numeric', 'min:1', 'max:10000'],
        'date' => ['nullable', 'date'],
        'paid' => ['nullable', 'in:0,1,true,false,on'],
    ]);

    $invoices = app(\App\Services\SourcingInvoice::class);
    $invoice = $invoices->create(
        $data['client_name'],
        (float) $data['amount'],
        \Carbon\Carbon::parse($data['date'] ?? now()),
        $request->session()->get('sigou.signer'),
        ! in_array((string) ($data['paid'] ?? '1'), ['0', 'false'], true),
    );

    return response($invoices->pdf($invoice), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . \App\Services\SourcingInvoice::filename($invoice) . '"',
        'Cache-Control' => 'private, no-store',
    ]);
})->name('invoice.pdf');

Route::middleware('auth')->get('/tools/sourcing-agreement/template', function () {
    return response(app(\App\Services\SourcingAgreement::class)->blank(), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . \App\Services\SourcingAgreement::filename() . '"',
    ]);
})->name('agreement.template');

// Supplier room photos live in private Drive folders, so they are streamed
// through here with the service account rather than linked directly. Only ids
// discovered while indexing a supplier folder are servable, so this cannot be
// used to fetch arbitrary Drive files.
Route::get('/supplier-photo/{fileId}', function (string $fileId) {
    $photos = app(\App\Services\SupplierPhotoService::class);

    if (! $photos->isAllowed($fileId)) {
        abort(404);
    }

    // Already on disk: hand it to nginx and let this worker go. Reading a
    // couple of hundred KB into PHP and echoing it back held one of only a
    // handful of workers for the whole transfer, so a page full of photos
    // queued behind itself.
    if ($cached = $photos->cachedFile($fileId)) {
        return response('', 200, [
            'Content-Type' => $cached['mime'],
            'Cache-Control' => 'public, max-age=2592000, immutable',
            'X-Accel-Redirect' => '/internal-supplier-photos/' . $cached['relative'],
        ]);
    }

    $file = $photos->download($fileId);
    if (! $file) {
        abort(404);
    }

    return response($file['body'], 200, [
        'Content-Type' => $file['mime'],
        'Cache-Control' => 'public, max-age=2592000, immutable',
    ]);
})->where('fileId', '[A-Za-z0-9_-]+')->name('supplier.photo');

Route::get('/rental-codes/agent-earnings', [RentalCodeController::class, 'agentEarnings'])->name('rental-codes.agent-earnings');
// New ID-based payroll route
Route::get('/rental-codes/agent-payroll/{agentId}', [RentalCodeController::class, 'agentPayrollById'])
    ->whereNumber('agentId')
    ->name('rental-codes.agent-payroll');

// Legacy name-based payroll route (kept as separate named route)
Route::get('/rental-codes/agent-payroll-by-name/{agentName}', [RentalCodeController::class, 'agentPayrollNew'])
    ->name('rental-codes.agent-payroll-by-name');

// Backwards-compatible aliases for older/typo URLs
Route::get('/rental-codes/agent-comissionfile/{agentName}', function ($agentName) {
    return redirect()->route('rental-codes.agent-payroll-by-name', ['agentName' => $agentName]);
});
Route::get('/rental-codes/agent-commissionfile/{agentName}', function ($agentName) {
    return redirect()->route('rental-codes.agent-payroll-by-name', ['agentName' => $agentName]);
});

// Old path redirect helper to ID-based when possible
Route::get('/rental-codes/agent-payroll-name/{agentName}', function ($agentName) {
    $id = \App\Models\User::where('name', $agentName)->value('id');
    if ($id) {
        return redirect()->route('rental-codes.agent-payroll', ['agentId' => $id]);
    }
    return redirect()->route('rental-codes.agent-payroll-by-name', ['agentName' => $agentName]);
});

// Public client intake (no auth required)
Route::get('/apply', [PublicClientController::class, 'create'])->name('public.client.create');
Route::post('/apply', [PublicClientController::class, 'store'])->name('public.client.store');

// Storage file serving route
Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);
    
    if (!file_exists($filePath)) {
        abort(404);
    }
    
    $mimeType = mime_content_type($filePath);
    $fileSize = filesize($filePath);
    
    return response()->file($filePath, [
        'Content-Type' => $mimeType,
        'Content-Length' => $fileSize,
    ]);
})->where('path', '.*')->name('storage.serve');
    
// Temporary public route for testing rental code generation
Route::get('/test-rental-code', function () {
    try {
        $lastRentalCode = \App\Models\RentalCode::orderBy('id', 'desc')->first();
        
        if (!$lastRentalCode) {
            // First rental code starts from CC0121
            $nextNumber = 121;
        } else {
            // Extract number from last code (e.g., "CC0121" -> 121)
            preg_match('/CC(\d+)/', $lastRentalCode->rental_code, $matches);
            if (isset($matches[1])) {
                $lastNumber = (int)$matches[1];
                // If the last number is less than 121, start from 121
                $nextNumber = $lastNumber >= 121 ? $lastNumber + 1 : 121;
            } else {
                // If no valid number found, start from 121
                $nextNumber = 121;
            }
        }
        
        // Format as CC0121, CC0122, etc.
        $newCode = 'CC' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        
        return response()->json(['code' => $newCode]);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to generate rental code: ' . $e->getMessage()], 500);
    }
});

// WhatsApp test route
Route::get('/test-whatsapp', function () {
    $sid    = env('TWILIO_ACCOUNT_SID');
    $token  = env('TWILIO_AUTH_TOKEN');
    $testNumber = env('TEST_WHATSAPP_NUMBER');
    $whatsappNumber = env('TWILIO_WHATSAPP_NUMBER');
    
    // Check if test number is configured
    if (!$testNumber || $testNumber === 'whatsapp:+1234567890') {
        return response()->json([
            'error' => 'Test WhatsApp number not configured',
            'message' => 'Please update TEST_WHATSAPP_NUMBER in your .env file with a real WhatsApp number',
            'format' => 'Use format: whatsapp:+1234567890 (include country code)',
            'example' => 'whatsapp:+447123456789 for UK number'
        ], 400);
    }
    
    try {
        $twilio = new Client($sid, $token);

        $message = $twilio->messages
            ->create(
                $testNumber, // to
                [
                    "from" => $whatsappNumber,
                    "body" => "✅ Hello from Laravel CRM! Your Twilio WhatsApp setup is working perfectly."
                ]
            );

        return response()->json([
            'success' => true,
            'message' => 'WhatsApp message sent successfully!',
            'sid' => $message->sid,
            'to' => $testNumber,
            'from' => $whatsappNumber
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'error' => 'Failed to send WhatsApp message',
            'message' => $e->getMessage(),
            'details' => [
                'to' => $testNumber,
                'from' => $whatsappNumber,
                'account_sid' => $sid ? 'Configured' : 'Missing'
            ]
        ], 500);
    }
});

// Test: send latest rental via WhatsApp (template by default; mode=text for plain)
Route::middleware('auth')->get('/admin/test-rental-template', function (\Illuminate\Http\Request $request) {
    $to = $request->query('to', config('services.twilio.test_whatsapp_number'));
    $mode = $request->query('mode', 'template');
    $latest = \App\Models\RentalCode::with('client','marketingAgentUser')->orderBy('id','desc')->first();
    if (!$latest) {
        return response()->json(['error' => 'No rental codes found'], 404);
    }
    $client = $latest->client;
    if ($mode === 'text') {
        $result = app(\App\Services\WhatsAppService::class)->sendRentalPlainTo($to ?: ($client->phone_number ?? ''), $latest, $client);
    } elseif ($mode === 'dummy') {
        $vars = [
            'rental_code' => 'Alpha BRAVO Charlie Delta Echo',
            'rentalcode_details' => 'Quick brown fox jumps swiftly',
            'clientprofile' => 'Client profile sample five words',
            'agent' => 'Agent name sample five words',
            'marketing_agent' => 'Marketing agent sample five words',
        ];
        $result = app(\App\Services\WhatsAppService::class)->sendTemplateWithVariables($to, $vars);
    } elseif ($mode === 'dummy_numeric') {
        $vars = [
            '1' => 'Alpha BRAVO Charlie Delta Echo',
            '2' => 'Quick brown fox jumps swiftly',
            '3' => 'Client profile sample five words',
            '4' => 'Agent name sample five words',
            '5' => 'Marketing agent sample five words',
        ];
        $result = app(\App\Services\WhatsAppService::class)->sendTemplateWithVariables($to, $vars);
    } else {
        $result = app(\App\Services\WhatsAppService::class)->sendRentalTemplateTo($to ?: ($client->phone_number ?? ''), $latest, $client);
    }
    return response()->json(['result' => $result, 'mode' => $mode]);
})->name('admin.test-rental-template');

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', function (Request $request) {
        // Store the intended URL if provided
        if ($request->has('redirect')) {
            session(['url.intended' => $request->get('redirect')]);
        }
        return view('auth.login');
    })->name('login');
    
    Route::post('/login', function (Request $request) {
        $input = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required'],
        ]);

        // Agents sign in with a short username (e.g. "agent") rather than a full
        // address, so resolve a bare identifier to the matching account's email.
        $identifier = trim($input['email']);
        if (! str_contains($identifier, '@')) {
            $resolved = \App\Models\User::where('email', 'like', $identifier . '@%')
                ->orderBy('id')
                ->value('email');
            if ($resolved) {
                $identifier = $resolved;
            }
        }

        $credentials = ['email' => $identifier, 'password' => $input['password']];

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            // Redirect to the intended URL, or default to /admin
            return redirect()->intended('/admin');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    })->name('login.post');
});

// Admin routes - require authentication
Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/properties', [AdminController::class, 'properties'])->name('admin.properties');
    Route::get('/properties/create', [AdminController::class, 'create'])->name('admin.properties.create');
    Route::post('/properties', [AdminController::class, 'store'])->name('admin.properties.store');
    Route::get('/properties/{property}/edit', [AdminController::class, 'edit'])->name('admin.properties.edit');
    Route::put('/properties/{property}', [AdminController::class, 'update'])->name('admin.properties.update');
    Route::patch('/properties/{property}/toggle-updatable', [AdminController::class, 'toggleUpdatable'])->name('admin.properties.toggle-updatable');
    Route::delete('/properties/{property}', [AdminController::class, 'destroy'])->name('admin.properties.destroy');
    Route::post('/upload-image', [AdminController::class, 'uploadImage'])->name('admin.upload-image');
    Route::get('/properties/flags', [AdminController::class, 'flags'])->name('admin.properties.flags');
    Route::post('/properties/{propertyId}/update-flag', [AdminController::class, 'updateFlag'])->name('admin.properties.update-flag');

    // Property interested clients
    Route::post('/properties/{property}/interests', [AdminController::class, 'addInterestedClient'])->name('admin.properties.interests.add');
    Route::delete('/properties/{property}/interests/{client}', [AdminController::class, 'removeInterestedClient'])->name('admin.properties.interests.remove');

    // Group Viewings
    Route::get('/group-viewings', [GroupViewingController::class, 'index'])->name('admin.group-viewings.index');
    Route::get('/group-viewings/create', [GroupViewingController::class, 'create'])->name('admin.group-viewings.create');
    Route::post('/group-viewings', [GroupViewingController::class, 'store'])->name('admin.group-viewings.store');
    Route::get('/group-viewings/{groupViewing}/attendees', [GroupViewingController::class, 'attendees'])->name('admin.group-viewings.attendees');
    
    // User Management Routes
    Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
    Route::get('/users/create', [AdminController::class, 'createUser'])->name('admin.users.create');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('admin.users.edit');
    Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');
    
    // Client Management Routes
    Route::get('/clients', [AdminController::class, 'clients'])->name('admin.clients');
    Route::get('/clients/create', [AdminController::class, 'createClient'])->name('admin.clients.create');
    Route::post('/clients', [AdminController::class, 'storeClient'])->name('admin.clients.store');
    Route::get('/clients/{client}/edit', [AdminController::class, 'editClient'])->name('admin.clients.edit');
    Route::put('/clients/{client}', [AdminController::class, 'updateClient'])->name('admin.clients.update');
    Route::delete('/clients/{client}', [AdminController::class, 'destroyClient'])->name('admin.clients.destroy');
    Route::post('/clients/{client}/toggle-registration', [AdminController::class, 'toggleRegistrationStatus'])->name('admin.clients.toggle-registration');
    
    // Rental Code Management Routes
    // Specific routes must be defined BEFORE resource routes to avoid conflicts
    Route::get('/rental-codes/export', [RentalCodeController::class, 'export'])->name('rental-codes.export');
    Route::get('/rental-codes/generate-code', [RentalCodeController::class, 'generateCode'])->name('rental-codes.generate-code');
    
    // File download and view routes - MUST be before resource route to avoid conflicts
    Route::get('/rental-codes/{rentalCode}/download/{field}/{index?}', [RentalCodeController::class, 'downloadFile'])->name('rental-codes.download-file');
    Route::get('/rental-codes/{rentalCode}/view/{field}/{index?}', [RentalCodeController::class, 'viewFile'])->name('rental-codes.view-file');
    
    Route::resource('rental-codes', RentalCodeController::class);
    Route::post('/rental-codes/{rentalCode}/mark-paid', [RentalCodeController::class, 'markAsPaid'])->name('rental-codes.mark-paid');
    Route::post('/rental-codes/{rentalCode}/mark-unpaid', [RentalCodeController::class, 'markAsUnpaid'])->name('rental-codes.mark-unpaid');
    Route::post('/rental-codes/{rentalCode}/update-status', [RentalCodeController::class, 'updateStatus'])->name('rental-codes.update-status');
    Route::post('/rental-codes/{rentalCode}/update-refunded', [RentalCodeController::class, 'updateRefunded'])->name('rental-codes.update-refunded');
    Route::post('/rental-codes/bulk-update-status', [RentalCodeController::class, 'bulkUpdateStatus'])->name('rental-codes.bulk-update-status');
    Route::post('/rental-codes/bulk-mark-paid', [RentalCodeController::class, 'bulkMarkPaid'])->name('rental-codes.bulk-mark-paid');
    Route::post('/rental-codes/approve-all-pending', [RentalCodeController::class, 'approveAllPending'])->name('rental-codes.approve-all-pending');
    
    // Landlord Bonus Management Routes
    Route::resource('landlord-bonuses', \App\Http\Controllers\LandlordBonusController::class);
    Route::post('/landlord-bonuses/bulk-mark-paid', [\App\Http\Controllers\LandlordBonusController::class, 'bulkMarkPaid'])->name('landlord-bonuses.bulk-mark-paid');
    Route::post('/landlord-bonuses/bulk-update-status', [\App\Http\Controllers\LandlordBonusController::class, 'bulkUpdateStatus'])->name('landlord-bonuses.bulk-update-status');
    Route::post('/landlord-bonuses/generate-invoice', [\App\Http\Controllers\LandlordBonusController::class, 'generateInvoice'])->name('landlord-bonuses.generate-invoice');
    Route::get('/landlord-bonuses-export', [\App\Http\Controllers\LandlordBonusController::class, 'export'])->name('landlord-bonuses.export');
    Route::get('/rental-codes/agent/{agentName}', [RentalCodeController::class, 'agentDetails'])->name('rental-codes.agent-details');
    Route::get('/rental-codes/{rentalCode}/details', [RentalCodeController::class, 'getRentalDetails'])->name('rental-codes.details');
    
    
    // Marketing Agent Management Routes
    Route::get('/marketing-agents', [RentalCodeController::class, 'marketingAgents'])->name('marketing-agents.index');
    Route::post('/marketing-agents', [RentalCodeController::class, 'storeMarketingAgent'])->name('marketing-agents.store');
    Route::delete('/marketing-agents/{user}', [RentalCodeController::class, 'removeMarketingAgent'])->name('marketing-agents.remove');
    
    // Invoice Management Routes
    Route::resource('invoices', InvoiceController::class)->names([
        'index' => 'admin.invoices.index',
        'create' => 'admin.invoices.create',
        'store' => 'admin.invoices.store',
        'show' => 'admin.invoices.show',
        'edit' => 'admin.invoices.edit',
        'update' => 'admin.invoices.update',
        'destroy' => 'admin.invoices.destroy'
    ]);
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('admin.invoices.pdf');
    Route::post('/invoices/{invoice}/mark-sent', [InvoiceController::class, 'markAsSent'])->name('admin.invoices.mark-sent');
    Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markAsPaid'])->name('admin.invoices.mark-paid');
    Route::post('/invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('admin.invoices.duplicate');
    
    // Call Log Management Routes - Specific routes first
    Route::get('/call-logs/stats', [CallLogController::class, 'stats'])->name('admin.call-logs.stats');
    Route::get('/call-logs/follow-ups', [CallLogController::class, 'followUps'])->name('admin.call-logs.follow-ups');
    Route::get('/call-logs/recent', [CallLogController::class, 'recent'])->name('admin.call-logs.recent');
    Route::get('/call-logs/check-phone', [CallLogController::class, 'checkPhone'])->name('admin.call-logs.check-phone');
    Route::get('/call-logs/previous-calls', [CallLogController::class, 'getPreviousCalls'])->name('admin.call-logs.previous-calls');
    Route::post('/call-logs/{call_log}/update-next-step', [CallLogController::class, 'updateNextStep'])->name('admin.call-logs.update-next-step');
    
    // AP Properties (full flats) Management Routes
    Route::resource('ap-properties', ApPropertyController::class)->names([
        'index' => 'admin.ap-properties.index',
        'create' => 'admin.ap-properties.create',
        'store' => 'admin.ap-properties.store',
        'show' => 'admin.ap-properties.show',
        'edit' => 'admin.ap-properties.edit',
        'update' => 'admin.ap-properties.update',
        'destroy' => 'admin.ap-properties.destroy',
    ]);
    Route::delete('/ap-properties/{ap_property}/images/{index}', [ApPropertyController::class, 'destroyImage'])->name('admin.ap-properties.images.destroy');
    
    // Admin Permissions Management
    // New simplified permission system
    Route::get('/user-permissions', [UserPermissionController::class, 'index'])->name('admin.user-permissions.index')->middleware('admin.permission:admin_permissions,view');
    Route::get('/user-permissions/{user}/edit', [UserPermissionController::class, 'edit'])->name('admin.user-permissions.edit')->middleware('admin.permission:admin_permissions,edit');
    Route::put('/user-permissions/{user}', [UserPermissionController::class, 'update'])->name('admin.user-permissions.update')->middleware('admin.permission:admin_permissions,edit');
    Route::delete('/user-permissions/{user}/reset', [UserPermissionController::class, 'reset'])->name('admin.user-permissions.reset')->middleware('admin.permission:admin_permissions,delete');
    
    // Old complex permission system (keeping for now)
    Route::get('/permissions', [App\Http\Controllers\AdminPermissionController::class, 'index'])->name('admin.permissions.index')->middleware('admin.permission:admin_permissions,view');
    Route::get('/permissions/{user}/edit', [App\Http\Controllers\AdminPermissionController::class, 'edit'])->name('admin.permissions.edit')->middleware('admin.permission:admin_permissions,edit');
    Route::put('/permissions/{user}', [App\Http\Controllers\AdminPermissionController::class, 'update'])->name('admin.permissions.update')->middleware('admin.permission:admin_permissions,edit');
    Route::delete('/permissions/{user}/reset', [App\Http\Controllers\AdminPermissionController::class, 'reset'])->name('admin.permissions.reset')->middleware('admin.permission:admin_permissions,delete');
    
    // Resource routes after specific routes
    Route::resource('call-logs', CallLogController::class)->names([
        'index' => 'admin.call-logs.index',
        'create' => 'admin.call-logs.create',
        'store' => 'admin.call-logs.store',
        'show' => 'admin.call-logs.show',
        'edit' => 'admin.call-logs.edit',
        'update' => 'admin.call-logs.update',
        'destroy' => 'admin.call-logs.destroy'
    ]);
    
    // Cash Document Management Routes (Merged with Rental Codes)
    Route::get('/cash-documents', [RentalCodeCashDocumentController::class, 'index'])->name('rental-codes.cash-documents.index');
    Route::get('/rental-codes/{rentalCode}/cash-documents/create', [RentalCodeCashDocumentController::class, 'create'])->name('rental-codes.cash-documents.create');
    Route::post('/rental-codes/{rentalCode}/cash-documents', [RentalCodeCashDocumentController::class, 'store'])->name('rental-codes.cash-documents.store');
    Route::get('/rental-codes/{rentalCode}/cash-documents', [RentalCodeCashDocumentController::class, 'show'])->name('rental-codes.cash-documents.show');
    Route::get('/rental-codes/{rentalCode}/cash-documents/edit', [RentalCodeCashDocumentController::class, 'edit'])->name('rental-codes.cash-documents.edit');
    Route::put('/rental-codes/{rentalCode}/cash-documents', [RentalCodeCashDocumentController::class, 'update'])->name('rental-codes.cash-documents.update');
    Route::delete('/rental-codes/{rentalCode}/cash-documents', [RentalCodeCashDocumentController::class, 'destroy'])->name('rental-codes.cash-documents.destroy');
    Route::post('/rental-codes/{rentalCode}/cash-documents/approve', [RentalCodeCashDocumentController::class, 'approve'])->name('rental-codes.cash-documents.approve');
    Route::post('/rental-codes/{rentalCode}/cash-documents/reject', [RentalCodeCashDocumentController::class, 'reject'])->name('rental-codes.cash-documents.reject');
    
    // Legacy cash-documents routes (redirect to new structure)
    Route::get('/cash-documents/create', function () {
        return redirect()->route('rental-codes.cash-documents.index');
    })->name('cash-documents.create');
});

// Profile routes - require authentication
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Global dashboard route (used by auth scaffolding and public views)
    Route::get('/dashboard', [AgentProfileController::class, 'dashboard'])->name('dashboard');
    
    // Agent Profile Routes
    Route::prefix('agent')->name('agent.profile.')->group(function () {
        Route::get('/dashboard', [AgentProfileController::class, 'dashboard'])->name('dashboard');
        Route::get('/rental-codes', [AgentProfileController::class, 'rentalCodes'])->name('rental-codes');
        Route::get('/earnings', [AgentProfileController::class, 'earnings'])->name('earnings');
        Route::get('/deductions', [AgentProfileController::class, 'deductions'])->name('deductions');
        Route::get('/clients', [AgentProfileController::class, 'clients'])->name('clients');
    });
    
        // Scraper Routes
        Route::get('/scraper', [ScraperController::class, 'index'])->name('admin.scraper.index');
        Route::post('/scraper/add-profile', [ScraperController::class, 'addProfile'])->name('admin.scraper.add-profile');
        Route::post('/scraper/remove-profile', [ScraperController::class, 'removeProfile'])->name('admin.scraper.remove-profile');
        Route::post('/scraper/run', [ScraperController::class, 'runScraper'])->name('admin.scraper.run');
        Route::post('/scraper/run-php', [PhpScraperController::class, 'runPhpScraper'])->name('admin.scraper.run-php');
        Route::post('/scraper/import', [ScraperController::class, 'importData'])->name('admin.scraper.import');
});

// Logout route
Route::post('/logout', function () {
    auth()->logout();
    return redirect('/');
})->middleware('auth')->name('logout');

// Localhost fallback for AP public views (when not using subdomain)
Route::prefix('ap')->group(function () {
    Route::get('/', [ApPublicPropertyController::class, 'index'])->name('ap.local.index');
    Route::get('/properties', [ApPublicPropertyController::class, 'index'])->name('ap.local.list');
    Route::get('/properties/{ap_property}', [ApPublicPropertyController::class, 'show'])->name('ap.local.show');
});

// Public AP Properties on subdomain ap.truehold.co.uk
Route::domain('ap.truehold.co.uk')->group(function () {
    Route::get('/', [ApPublicPropertyController::class, 'index'])->name('ap.public.index');
    Route::get('/properties/{ap_property}', [ApPublicPropertyController::class, 'show'])->name('ap.public.show');
});
