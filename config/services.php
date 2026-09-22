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

    'harborops' => [
        // Primary property feed: Harbor Ops scraped-listings public API
        'base_url' => env('HARBOROPS_API_URL'),
        'api_key' => env('HARBOROPS_API_KEY'),
        'cache_timeout' => env('HARBOROPS_CACHE_TIMEOUT', 300), // 5 minutes
        // Portal domain used to build login-gated landlord deep links (/go/landlord/<id>)
        'portal_domain' => env('HARBOROPS_PORTAL_DOMAIN', 'harborops.co.uk'),

        // Only listings whose status is in this list are shown. The upstream feed
        // has no "let"/"taken" value, so this is an allowlist rather than a blocklist.
        // Comma-separated, case-insensitive.
        'available_statuses' => env('HARBOROPS_AVAILABLE_STATUSES', 'available'),
        // Roughly half the feed has no status at all. Upstream treated blank as
        // available; set this to true to keep that behaviour.
        'include_blank_status' => env('HARBOROPS_INCLUDE_BLANK_STATUS', false),
        // Hide listings the upstream scraper has not re-checked in this many days
        // (0 disables). Staleness, not status, is what surfaces already-let rooms.
        'max_age_days' => (int) env('HARBOROPS_MAX_AGE_DAYS', 0),
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
