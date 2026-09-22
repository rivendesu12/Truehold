<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
        'sheets' => [
            'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
            'sheet_name' => env('GOOGLE_SHEETS_SHEET_NAME', 'Sheet1'),
            'credentials_path' => env('GOOGLE_SHEETS_CREDENTIALS_PATH'),
            'credentials_json' => env('GOOGLE_SHEETS_CREDENTIALS_JSON'),
        ],
        'properties' => [
            'spreadsheet_id' => env('GOOGLE_PROPERTIES_SPREADSHEET_ID'),
            'sheet_name' => env('GOOGLE_PROPERTIES_SHEET_NAME', 'Properties'),
            'credentials_path' => env('GOOGLE_PROPERTIES_CREDENTIALS_PATH'),
            'credentials_json' => env('GOOGLE_PROPERTIES_CREDENTIALS_JSON'),
            'cache_timeout' => env('GOOGLE_PROPERTIES_CACHE_TIMEOUT', 300), // 5 minutes
        ],
    ],

    // TfL open data. Everything we use works without a key at roughly 50
    // requests a minute; an app key raises that to 500 and only speeds up the
    // transport:build-* commands, so it is optional.
    'tfl' => [
        'app_key' => env('TFL_APP_KEY'),
    ],

    // The office WiFi, which Sigou hands to agents for clients. In .env, not
    // here: the repository is public.
    'office_wifi' => [
        'ssid' => env('OFFICE_WIFI_SSID'),
        'password' => env('OFFICE_WIFI_PASSWORD'),
        'qr_url' => env('OFFICE_WIFI_QR_URL'),
    ],

    'harborops' => [
        // Primary property feed: Harbor Ops scraped-listings public API
        'base_url' => env('HARBOROPS_API_URL'),
        'api_key' => env('HARBOROPS_API_KEY'),
        'cache_timeout' => env('HARBOROPS_CACHE_TIMEOUT', 300), // 5 minutes
        // Portal domain used to build login-gated landlord deep links (/go/landlord/<id>)
        'portal_domain' => env('HARBOROPS_PORTAL_DOMAIN', 'harborops.co.uk'),

        // Only listings whose status is in this list are shown. The upstream feed
        // has no "let"/"taken" value, so this is an allowlist rather than a blocklist.
        // Comma-separated, case-insensitive, matched as a prefix. available,
        // ROLLING and APT BREAK are always shown (set in the service); this adds to them.
        'available_statuses' => env('HARBOROPS_AVAILABLE_STATUSES', 'available'),
        // Roughly half the feed has no status at all. Upstream treated blank as
        // available; set this to true to keep that behaviour.
        'include_blank_status' => env('HARBOROPS_INCLUDE_BLANK_STATUS', false),
        // Hide listings the upstream scraper has not re-checked in this many days
        // (0 disables). Staleness, not status, is what surfaces already-let rooms.
        'max_age_days' => (int) env('HARBOROPS_MAX_AGE_DAYS', 0),
        // Some feed rows are empty stubs: no title, no price, no advert. A card
        // with no title and "N/A" is worse than no card, so require both.
        'require_title_and_price' => env('HARBOROPS_REQUIRE_TITLE_AND_PRICE', true),
        // Agent filter is hidden for now rather than deleted; flip to true to restore.
        'show_agent_filter' => env('SHOW_AGENT_FILTER', false),
    ],

    // Supplier rooms from the "Room targets" spreadsheet, Targets tab. This is the
    // only source that includes every agency (Banksia, Javier, AP/Horizon, Soreva)
    // in one normalised shape.
    'supplier_targets' => [
        'spreadsheet_id' => env('SUPPLIER_TARGETS_SHEET_ID'),
        'tab' => env('SUPPLIER_TARGETS_TAB', 'Targets'),
        'credentials_path' => env('SUPPLIER_TARGETS_CREDENTIALS'),
        'cache_timeout' => env('SUPPLIER_TARGETS_CACHE_TIMEOUT', 900),
        // Availability horizon, matching the AP portfolio sync's 62 days.
        'window_days' => env('SUPPLIER_TARGETS_WINDOW_DAYS', 62),
        // Empty = all suppliers. Comma-separated to restrict.
        'suppliers' => env('SUPPLIER_TARGETS_SUPPLIERS', ''),
    ],

    // AP / Horizon portfolio workbook. A real .xlsx in Drive, so it is downloaded
    // and parsed rather than read through the Sheets API (which 400s on it).
    // Used to fill price gaps the Targets tab and the feed both have.
    'ap_portfolio' => [
        'file_id' => env('AP_PORTFOLIO_FILE_ID'),
    ],

    // Claude, for the agent search assistant. Haiku is the cheapest capable
    // model for turning a request into filters ($1/$5 per million tokens);
    // set ANTHROPIC_MODEL to claude-sonnet-5 or claude-opus-5 for more nuance.
    // Agent search assistant. The job is structured parsing against a fixed
    // JSON schema, which small models do well, so the cheapest tier is a
    // reasonable fit. Switch provider/model with env alone.
    //   openai  gpt-5-nano      $0.05/$0.40 per 1M  (~GBP 0.09 / 1k searches)
    //   openai  gpt-5.6-luna    $0.20/$1.20         (~GBP 0.30 / 1k)
    //   anthropic claude-haiku-4-5  $1.00/$5.00     (~GBP 1.30 / 1k)
    'assistant' => [
        'provider' => env('ASSISTANT_PROVIDER', 'openai'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5-nano'),
        // minimal | low | medium | high. Empty sends nothing (the model's
        // own default, medium, which spends far more on hidden thinking).
        'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'low'),
        // USD per 1M tokens, for assistant:usage estimates (gpt-5.6-luna).
        'price' => [
            'input' => (float) env('OPENAI_PRICE_INPUT', 0.20),
            'cached' => (float) env('OPENAI_PRICE_CACHED', 0.02),
            'output' => (float) env('OPENAI_PRICE_OUTPUT', 1.20),
        ],
        // Any provider exposing an OpenAI-compatible /chat/completions
        // endpoint can be used by pointing this at their base URL. The
        // assistant only needs strict JSON-schema output; nothing else here
        // is OpenAI-specific.
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'phone_number' => env('TWILIO_PHONE_NUMBER'),
        'whatsapp_number' => env('TWILIO_WHATSAPP_NUMBER'),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'test_whatsapp_number' => env('TEST_WHATSAPP_NUMBER'),
        'admin_whatsapp_number' => env('ADMIN_WHATSAPP_NUMBER', '+447947768707'),
        // Approved WhatsApp Content Template SID (HX...)
        'rental_template_sid' => env('TWILIO_RENTAL_TEMPLATE_SID', 'HXe7a5d7e1a64ec70a35cf674d9b0dd82d'),
        // Optional content language (e.g., en)
        'content_language' => env('TWILIO_CONTENT_LANGUAGE', 'en'),
    ],

	'zapier' => [
		// Primary key used by code
		'zapier_webhook_url' => env('ZAPIER_WEBHOOK_URL'),
		// Backward compatibility
		'rental_code_webhook_url' => env('ZAPIER_RENTAL_CODE_WEBHOOK_URL'),
	],

    'wasender' => [
        'key' => env('WASENDER_API_KEY'),
        'base_url' => env('WASENDER_API_BASE', 'https://wasenderapi.com'),
        'group_jid' => env('WASENDER_GROUP_JID'),
    ],

];
