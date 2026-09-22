<?php

namespace App\Services;

use Anthropic\Client as AnthropicClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns an agent's plain-English request into concrete search filters.
 *
 * The model only parses the request into a filter spec — it never sees the
 * listings and never invents one. Matching is then done by our own code against
 * the real feed, so an answer can't be hallucinated: every result is a genuine
 * listing that satisfied the parsed criteria.
 */
class AgentSearchAssistant
{
    /** Landmarks agents ask about by name, for "X minutes from Y" queries. */
    private const LANDMARKS = [
        'bond street' => [51.5142, -0.1494],
        'oxford circus' => [51.5152, -0.1418],
        'liverpool street' => [51.5178, -0.0823],
        'kings cross' => [51.5308, -0.1238],
        "king's cross" => [51.5308, -0.1238],
        'canary wharf' => [51.5054, -0.0235],
        'waterloo' => [51.5033, -0.1145],
        'victoria' => [51.4952, -0.1441],
        'london bridge' => [51.5049, -0.0863],
        'paddington' => [51.5154, -0.1755],
        'stratford' => [51.5416, -0.0042],
        'shoreditch' => [51.5265, -0.0784],
        'central london' => [51.5074, -0.1278],
    ];

    /**
     * Rough tube speed including walking and waiting: a minute of travel is
     * about a third of a mile. Deliberately generous — better to show a few
     * extra than to hide something an agent would have offered.
     */
    private const MILES_PER_MINUTE = 0.33;

    /** How far to widen when an exact area match finds nothing, in order. */
    private const WIDEN_MILES = 2.0;
    private const WIDEN_STEPS = [2.0, 4.0, 7.0];

    public function provider(): string
    {
        return config('services.assistant.provider', 'openai') === 'anthropic' ? 'anthropic' : 'openai';
    }

    public function model(): string
    {
        return $this->provider() === 'anthropic'
            ? (string) config('services.anthropic.model', 'claude-haiku-4-5')
            : (string) config('services.openai.model', 'gpt-5-nano');
    }

    public function isConfigured(): bool
    {
        return $this->provider() === 'anthropic'
            ? ! empty(config('services.anthropic.api_key'))
            : ! empty(config('services.openai.api_key'));
    }

    /**
     * Parse a request into a filter spec.
     *
     * @return array{filters: array, explanation: string, commission_only: bool, sort_commission_first: bool}|null
     */
    public function parse(string $question, array $knownLocations = []): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $locationHint = $knownLocations
            ? "\n\nAreas that exist in our data (prefer these for `location`): "
                . implode(', ', array_slice($knownLocations, 0, 120))
            : '';

        $system = <<<'SYS'
You convert a letting agent's plain-English request into search filters for a
London room-and-flat listings site. You never invent listings; you only produce
filters. Return JSON only.

Rules:
- `property_types` uses exactly these values: full_property (a whole flat or
  house), studio, rooms (any room in a shared property, including en-suite).
  Empty array means no type preference.
- `ensuite_only` true only if they explicitly want an en-suite / own bathroom.
- `max_price` / `min_price` are monthly rent in GBP.
- `max_bedrooms` when they want a small share ("max 3 bed flat", "not sharing
  with lots of people"). `min_bedrooms` is rare.
- For "N minutes from X" or "near X", set `near_landmark` to X and
  `minutes_from_landmark` to N. If they say a distance in miles instead, set
  `radius_miles`. If they name one of our own areas, set `location`.
- Tube zones: zone 1 is central. Treat "up to zone 2" as within about 5 miles
  of central London, zone 3 as about 8 miles: set near_landmark to
  "central london" and radius_miles accordingly.
- `commission_only` true if they ask for only agencies that pay commission.
- `explanation` is one short sentence telling the agent how you read their
  request, so they can spot a misreading.
SYS;

        $schema = [
            'type' => 'object',
            'properties' => [
                'location' => ['type' => ['string', 'null']],
                'near_landmark' => ['type' => ['string', 'null']],
                'minutes_from_landmark' => ['type' => ['number', 'null']],
                'radius_miles' => ['type' => ['number', 'null']],
                'min_price' => ['type' => ['number', 'null']],
                'max_price' => ['type' => ['number', 'null']],
                'property_types' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'enum' => ['full_property', 'studio', 'rooms']],
                ],
                'ensuite_only' => ['type' => 'boolean'],
                'min_bedrooms' => ['type' => ['number', 'null']],
                'max_bedrooms' => ['type' => ['number', 'null']],
                'couples' => ['type' => ['boolean', 'null']],
                'commission_only' => ['type' => 'boolean'],
                'explanation' => ['type' => 'string'],
            ],
            'required' => [
                'location', 'near_landmark', 'minutes_from_landmark', 'radius_miles',
                'min_price', 'max_price', 'property_types', 'ensuite_only',
                'min_bedrooms', 'max_bedrooms', 'couples', 'commission_only', 'explanation',
            ],
            'additionalProperties' => false,
        ];

        try {
            $json = $this->provider() === 'anthropic'
                ? $this->askAnthropic($system . $locationHint, $question, $schema)
                : $this->askOpenAi($system . $locationHint, $question, $schema);

            if ($json === null) {
                return null;
            }

            $spec = json_decode($json, true);
            return is_array($spec) ? $spec : null;
        } catch (\Throwable $e) {
            Log::warning('Agent search parse failed', [
                'provider' => $this->provider(),
                'model' => $this->model(),
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /** Anthropic path, via the official SDK. */
    protected function askAnthropic(string $system, string $question, array $schema): ?string
    {
        $client = new AnthropicClient(apiKey: config('services.anthropic.api_key'));

        $message = $client->messages->create(
            model: $this->model(),
            maxTokens: 1024,
            system: $system,
            messages: [['role' => 'user', 'content' => $question]],
            outputConfig: ['format' => ['type' => 'json_schema', 'schema' => $schema]],
        );

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return $block->text;
            }
        }

        return null;
    }

    /**
     * OpenAI path. Raw HTTP because OpenAI publishes no official PHP SDK;
     * strict json_schema means the reply is schema-valid, not just JSON-ish.
     */
    protected function askOpenAi(string $system, string $question, array $schema): ?string
    {
        $response = Http::withToken((string) config('services.openai.api_key'))
            ->timeout(30)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model(),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $question],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'search_filters',
                        'strict' => true,
                        'schema' => $schema,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('OpenAI assistant call failed', [
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 300),
            ]);
            return null;
        }

        return $response->json('choices.0.message.content');
    }

    /**
     * Apply a parsed spec to the live feed.
     *
     * Runs the geography separately from everything else, so that when a brief
     * yields nothing we can retry with a wider area rather than hand back an
     * empty list. An exact-area match frequently survives on its own and then
     * gets emptied by a later filter: we hold 13 Canary Wharf listings and 22
     * studios, but no studio in Canary Wharf, while there is one 1.5 miles off.
     *
     * @return array{results: Collection, matched: int, center: ?array, radius: ?float, widened: bool}
     */
    public function search(array $spec, Collection $properties): array
    {
        $radius = $spec['radius_miles'] ?? null;
        $center = $this->resolveLandmark($spec['near_landmark'] ?? null);

        if ($center && ! empty($spec['minutes_from_landmark'])) {
            $radius = round($spec['minutes_from_landmark'] * self::MILES_PER_MINUTE, 2);
        }

        // "near Canary Wharf" with no distance given still has to mean near it.
        // Without this the landmark was resolved and then ignored, quietly
        // returning the whole feed.
        if ($center && ! $radius) {
            $radius = self::WIDEN_MILES;
        }

        $term = strtolower(trim((string) ($spec['location'] ?? '')));

        // Pass one: the geography as asked for.
        if ($center && $radius) {
            $scoped = $this->withinRadius($properties, $center, $radius);
        } elseif ($term !== '') {
            $scoped = $properties->filter($this->textMatcher($term));
        } else {
            $scoped = $properties;
        }

        $results = $this->applyPreferences($scoped, $spec);
        $widened = false;

        // Pass two: nothing matched, but a place was named — step the radius out
        // until something does. An agent would rather be told "nothing in Canary
        // Wharf, here are three a mile away" than be shown an empty list.
        if ($results->isEmpty() && ($term !== '' || $center)) {
            $wideCenter = $center ?: $this->centroidFor($properties, $term);

            if ($wideCenter) {
                foreach (self::WIDEN_STEPS as $step) {
                    if ($radius && $step <= (float) $radius) {
                        continue; // already covered by the original search
                    }

                    $candidate = $this->applyPreferences(
                        $this->withinRadius($properties, $wideCenter, $step),
                        $spec
                    );

                    if ($candidate->isNotEmpty()) {
                        $results = $candidate;
                        $center = $wideCenter;
                        $radius = $step;
                        $widened = true;
                        break;
                    }
                }
            }
        }

        // Commission-paying agencies first, always.
        $results = $results->sortByDesc(fn ($p) => $this->paysCommission($p) ? 1 : 0)->values();

        return [
            'results' => $results,
            'matched' => $results->count(),
            'center' => $center,
            'radius' => $radius,
            'widened' => $widened,
        ];
    }

    /** Everything that is not geography. */
    protected function applyPreferences(Collection $results, array $spec): Collection
    {
        if (! empty($spec['max_price'])) {
            $results = $results->filter(fn ($p) => ! empty($p['price']) && (float) $p['price'] <= (float) $spec['max_price']);
        }
        if (! empty($spec['min_price'])) {
            $results = $results->filter(fn ($p) => ! empty($p['price']) && (float) $p['price'] >= (float) $spec['min_price']);
        }

        $types = array_filter((array) ($spec['property_types'] ?? []));
        if ($types) {
            $results = $results->filter(fn ($p) =>
                in_array(\App\Support\PropertyClassifier::bucket($p), $types, true));
        }

        if (! empty($spec['ensuite_only'])) {
            $results = $results->filter(function ($p) {
                $hay = strtolower(($p['property_type'] ?? '') . ' ' . ($p['title'] ?? '') . ' ' . ($p['description'] ?? ''));
                return str_contains($hay, 'ensuite') || str_contains($hay, 'en-suite') || str_contains($hay, 'en suite');
            });
        }

        if (! empty($spec['max_bedrooms'])) {
            $results = $results->filter(fn ($p) =>
                ! empty($p['total_rooms']) && (int) $p['total_rooms'] <= (int) $spec['max_bedrooms']);
        }
        if (! empty($spec['min_bedrooms'])) {
            $results = $results->filter(fn ($p) =>
                ! empty($p['total_rooms']) && (int) $p['total_rooms'] >= (int) $spec['min_bedrooms']);
        }

        if (! empty($spec['commission_only'])) {
            $results = $results->filter(fn ($p) => $this->paysCommission($p));
        }

        return $results;
    }

    protected function textMatcher(string $term): callable
    {
        return fn ($p) => str_contains(strtolower((string) ($p['location'] ?? '')), $term)
            || str_contains(strtolower((string) ($p['title'] ?? '')), $term)
            || str_contains(strtolower((string) ($p['postcode'] ?? '')), $term);
    }

    protected function withinRadius(Collection $properties, array $center, float $miles): Collection
    {
        return $properties->filter(function ($p) use ($center, $miles) {
            if (! is_numeric($p['latitude'] ?? null) || ! is_numeric($p['longitude'] ?? null)) {
                return false;
            }
            return \App\Support\PropertyClassifier::milesBetween(
                (float) $p['latitude'], (float) $p['longitude'], $center[0], $center[1]
            ) <= $miles;
        });
    }

    /** A landmark we know, or null. */
    protected function resolveLandmark(?string $name): ?array
    {
        $needle = strtolower(trim((string) $name));
        if ($needle === '') {
            return null;
        }
        foreach (self::LANDMARKS as $landmark => $coords) {
            if (str_contains($needle, $landmark) || str_contains($landmark, $needle)) {
                return $coords;
            }
        }
        return null;
    }

    /** Centre of our own listings matching a term, else a known landmark. */
    protected function centroidFor(Collection $properties, string $term): ?array
    {
        if ($term === '') {
            return null;
        }

        // A known landmark is a truer centre than the mean of whatever happened
        // to mention the word — one stray match miles away skews an average.
        if ($landmark = $this->resolveLandmark($term)) {
            return $landmark;
        }

        $matching = $properties->filter($this->textMatcher($term))
            ->filter(fn ($p) => is_numeric($p['latitude'] ?? null) && is_numeric($p['longitude'] ?? null));

        if ($matching->isNotEmpty()) {
            return [
                (float) $matching->avg(fn ($p) => (float) $p['latitude']),
                (float) $matching->avg(fn ($p) => (float) $p['longitude']),
            ];
        }

        return null;
    }

    public function paysCommission(array $property): bool
    {
        $paying = $property['paying'] ?? null;
        if (is_string($paying)) {
            return strtolower(trim($paying)) === 'yes';
        }
        return (bool) $paying;
    }
}
