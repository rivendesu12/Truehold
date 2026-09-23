@extends('layouts.admin')

@section('title', 'Zoopla leads')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-3xl">
    <h1 class="text-3xl font-bold text-gray-900">Zoopla leads</h1>
    <p class="text-gray-600 mt-2">
        Every 5 minutes the site reads new Zoopla enquiries from <strong>{{ $mailbox }}</strong>
        and adds them to the <a href="{{ $sheetUrl }}" target="_blank" rel="noopener" class="text-blue-600 underline">Joy Homes - Zoopla leads</a> sheet.
        Emails are only read, never marked read or deleted.
    </p>

    @if (session('status'))
        <div class="mt-6 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3">{{ session('status') }}</div>
    @endif

    {{-- Last run --}}
    <div class="mt-6 bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900">Last check</h2>
        @if ($lastRun)
            <p class="mt-2 {{ $lastRun['ok'] ? 'text-green-700' : 'text-red-700' }}">
                {{ $lastRun['ok'] ? '✓' : '✗' }} {{ $lastRun['message'] }}
            </p>
            <p class="text-sm text-gray-500 mt-1">{{ \Carbon\Carbon::parse($lastRun['at'])->diffForHumans() }}</p>
        @elseif ($hasPassword)
            <p class="mt-2 text-gray-600">Waiting for the first check (within 5 minutes). Refresh this page.</p>
        @else
            <p class="mt-2 text-gray-600">Not running yet: it needs the Zoho password below.</p>
        @endif
    </div>

    {{-- Password --}}
    <div class="mt-6 bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900">Zoho password</h2>
        <p class="text-gray-600 mt-1 text-sm">
            The password for {{ $mailbox }}. If Zoho has two-step verification on, use an app password
            (Zoho → My Account → Security → App passwords). Stored encrypted; it is never shown again.
        </p>
        <p class="mt-3 text-sm {{ $hasPassword ? 'text-green-700' : 'text-gray-500' }}">
            {{ $hasPassword ? '✓ A password is saved' . ($passwordFromEnv ? ' in the server settings (.env), which wins over this box' : '') . '.' : 'No password saved yet.' }}
        </p>

        <form method="POST" action="{{ route('admin.zoopla-leads.save') }}" class="mt-4 flex flex-col sm:flex-row gap-3">
            @csrf
            <input type="password" name="password" autocomplete="new-password" required
                   placeholder="{{ $hasPassword ? 'Enter a new password to replace it' : 'Zoho password' }}"
                   class="flex-1 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500">
            <button type="submit" class="px-6 py-2 rounded-lg font-medium text-white bg-gray-900 hover:bg-gray-700">Save</button>
        </form>
        @error('password')
            <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>

    {{-- Sharing --}}
    <div class="mt-6 bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900">Sheet access</h2>
        @if ($shareWith)
            <p class="text-gray-600 mt-1 text-sm">The sheet must be shared, as <strong>Editor</strong>, with the site's Google account:</p>
            <p class="mt-3 font-mono text-sm bg-gray-100 rounded px-3 py-2 break-all select-all">{{ $shareWith }}</p>
            <p class="text-gray-500 mt-2 text-xs">Open the sheet → Share → paste this address → Editor → untick "Notify" → Share.</p>
        @else
            <p class="mt-2 text-red-700 text-sm">The site's Google credentials are not set up on the server, so it cannot write to the sheet.</p>
        @endif
    </div>
</div>
@endsection
