<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Records every question put to Sigou and what came back, in one place
 * rather than at each of the endpoint's exits: what was asked, how it was
 * read, how many results in each band, tokens and time. Adds `log_id` to
 * the reply so the panel can report which result was opened.
 *
 * Our own database, so it costs nothing to keep. Never allowed to break a
 * search: a failure to log is logged and ignored.
 */
class LogSigouInteraction
{
    public function handle(Request $request, Closure $next)
    {
        $started = microtime(true);
        $response = $next($request);

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        try {
            $data = $response->getData(true);
            $spec = (array) $request->attributes->get('sigou.spec', []);
            $groups = (array) ($data['groups'] ?? []);

            $kind = match (true) {
                isset($data['error']) => 'error',
                isset($data['wifi']) || array_key_exists('wifi', $data) => 'wifi',
                isset($data['agreement']) => 'agreement',
                isset($data['invoice']) => 'invoice',
                isset($data['agency']) => 'agency',
                ! empty($data['chat']) => 'chat',
                default => 'search',
            };

            $id = DB::table('assistant_interactions')->insertGetId([
                'user_id' => $request->user()?->id,
                'session_key' => substr(hash('sha256', (string) $request->session()->getId()), 0, 16),
                'kind' => $kind,
                'query' => mb_substr((string) $request->input('q', ''), 0, 2000),
                'filters' => $kind === 'search' ? json_encode(\App\Services\AgentSearchAssistant::carriedFilters($spec)) : null,
                'refined' => ! empty($data['refined']),
                'best' => count($groups['commission'] ?? []) + count($groups['standard'] ?? []),
                'other' => count($groups['alternatives'] ?? []),
                'wild' => count($groups['wildcards'] ?? []),
                'sigou' => mb_substr((string) ($data['sigou'] ?? $data['error'] ?? ''), 0, 500),
                'usage' => json_encode($request->attributes->get('sigou.usage')),
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'created_at' => now(),
            ]);

            $data['log_id'] = $id;
            $response->setData($data);
        } catch (\Throwable $e) {
            Log::warning('Could not log Sigou interaction', ['error' => $e->getMessage()]);
        }

        return $response;
    }
}
