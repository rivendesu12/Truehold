<?php

namespace App\Http\Controllers;

use App\Services\ZooplaLeadsSheet;
use App\Support\ZooplaLeadsSettings;
use Illuminate\Http\Request;

/**
 * Where the Zoho password for `zoopla:leads` is entered, so nobody has to
 * touch .env. Admins only. The page never shows the password back and never
 * contacts Zoho or Google itself: the scheduled command does, every five
 * minutes, and the page shows how its last run went.
 */
class ZooplaLeadsController extends Controller
{
    public function show(Request $request, ZooplaLeadsSheet $sheet)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $config = config('services.zoopla_leads');

        return view('admin.zoopla-leads', [
            'mailbox' => $config['username'],
            'hasPassword' => filled(ZooplaLeadsSettings::password()),
            'passwordFromEnv' => filled($config['password']),
            'lastRun' => ZooplaLeadsSettings::lastRun(),
            'sheetUrl' => 'https://docs.google.com/spreadsheets/d/' . $config['spreadsheet_id'] . '/edit',
            'shareWith' => $sheet->serviceAccountEmail(),
        ]);
    }

    public function save(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate(['password' => ['required', 'string', 'max:200']]);
        ZooplaLeadsSettings::savePassword(trim($data['password']));

        return redirect()->route('admin.zoopla-leads')
            ->with('status', 'Saved. The next check runs within 5 minutes; refresh this page to see how it went.');
    }
}
