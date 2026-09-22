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
     * Straight-line miles per minute of journey time.
     *
     * This is a proxy, not a travel time: it cannot know that Wembley Park is
     * ~25 minutes on the Jubilee while Canons Park, barely further out, is
     * closer to 50. 0.22 keeps the circle near zone 3 for a 30-minute ask
     * rather than throwing it out to 10 miles, and callers are told the figure
     * is a distance so it is never presented as a journey time.
     */
    private const MILES_PER_MINUTE = 0.22;

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

        // The brief can carry two distance constraints at once — "up to zone 3"
        // and "within 30 minutes of Bond Street". Honour the tighter of the two
        // rather than letting one overwrite the other.
        if ($center && ! empty($spec['minutes_from_landmark'])) {
            $fromMinutes = round($spec['minutes_from_landmark'] * self::MILES_PER_MINUTE, 2);
            $radius = $radius ? min((float) $radius, $fromMinutes) : $fromMinutes;
        }

        // "near Canary Wharf" with no distance given still has to mean near it.
        if ($center && ! $radius) {
            $radius = self::WIDEN_MILES;
        }

        $term = strtolower(trim((string) ($spec['location'] ?? '')));

        // Attempt order, stopping at the first that returns anything:
        //   1. the brief exactly as given
        //   2. the same brief with the area stepped out 2 / 4 / 7 miles
        //   3. the same again without the bedroom filter, which the data often
        //      cannot answer at all (no whole property carries a bedroom count)
        $attempt = function (array $useSpec, ?array $useCenter, ?float $useRadius) use ($properties, $term) {
            if ($useCenter && $useRadius) {
                $scoped = $this->withinRadius($properties, $useCenter, $useRadius);
            } elseif ($term !== '') {
                $scoped = $properties->filter($this->textMatcher($term));
            } else {
                $scoped = $properties;
            }
            return $this->applyPreferences($scoped, $useSpec);
        };

        $withoutBeds = $spec;
        unset($withoutBeds['min_bedrooms'], $withoutBeds['max_bedrooms']);
        $hasBedFilter = ! empty($spec['min_bedrooms']) || ! empty($spec['max_bedrooms']);

        $wideCenter = $center ?: ($term !== '' ? $this->centroidFor($properties, $term) : null);
        $ladder = [];

        foreach (self::WIDEN_STEPS as $step) {
            if (! $wideCenter || ($radius && $step <= (float) $radius)) {
                continue;
            }
            $ladder[] = $step;
        }

        $plan = [['spec' => $spec, 'center' => $center, 'radius' => $radius, 'widened' => false, 'relaxed' => []]];

        foreach ($ladder as $step) {
            $plan[] = ['spec' => $spec, 'center' => $wideCenter, 'radius' => $step, 'widened' => true, 'relaxed' => []];
        }

        if ($hasBedFilter) {
            $plan[] = ['spec' => $withoutBeds, 'center' => $center, 'radius' => $radius, 'widened' => false, 'relaxed' => ['bedrooms']];
            foreach ($ladder as $step) {
                $plan[] = ['spec' => $withoutBeds, 'center' => $wideCenter, 'radius' => $step, 'widened' => true, 'relaxed' => ['bedrooms']];
            }
        }

        $results = collect();
        $widened = false;
        $relaxed = [];

        foreach ($plan as $try) {
            $candidate = $attempt($try['spec'], $try['center'], $try['radius']);
            if ($candidate->isNotEmpty()) {
                $results = $candidate;
                $center = $try['center'];
                $radius = $try['radius'];
                $widened = $try['widened'];
                $relaxed = $try['relaxed'];
                break;
            }
        }

        // Three groups, in the order an agent works through them: paying
        // agencies that meet the brief, then non-paying ones that meet it, then
        // near-misses worth mentioning with the reason they missed.
        $onBrief = $results->values();
        $alternatives = $this->alternativesFor($spec, $properties, $onBrief, $center, $radius);

        return [
            'results' => $onBrief,
            'matched' => $onBrief->count(),
            'commission' => $onBrief->filter(fn ($p) => $this->paysCommission($p))->values(),
            'standard' => $onBrief->reject(fn ($p) => $this->paysCommission($p))->values(),
            'alternatives' => $alternatives,
            'center' => $center,
            'radius' => $radius,
            'widened' => $widened,
            'relaxed' => $relaxed,
        ];
    }

    /**
     * Near-misses worth offering anyway: one constraint stretched at a time, so
     * every suggestion carries a plain reason ("GBP 120 over budget", "1.4 mi
     * further out") rather than appearing without explanation.
     *
     * Only ever stretches outwards, never swaps a requirement the client was
     * explicit about — an en-suite ask stays an en-suite ask.
     */
    protected function alternativesFor(
        array $spec,
        Collection $properties,
        Collection $onBrief,
        ?array $center,
        ?float $radius
    ): Collection {
        $seen = $onBrief->pluck('id')->filter()->flip();
        $out = collect();

        // A bit further out than asked.
        if ($center && $radius) {
            $wider = (float) $radius * 1.6;
            foreach ($this->applyPreferences($this->withinRadius($properties, $center, $wider), $spec) as $p) {
                if (isset($seen[$p['id'] ?? ''])) {
                    continue;
                }
                $d = \App\Support\PropertyClassifier::milesBetween(
                    (float) $p['latitude'], (float) $p['longitude'], $center[0], $center[1]
                );
                $p['why'] = sprintf('%.1f mi out, past the %s mi you asked for', $d, $radius);
                $out->push($p);
            }
        }

        // A bit over budget, but in the area they actually want.
        if (! empty($spec['max_price'])) {
            $stretched = $spec;
            $stretched['max_price'] = (float) $spec['max_price'] * 1.25;
            $scope = ($center && $radius)
                ? $this->withinRadius($properties, $center, $radius)
                : $properties;

            foreach ($this->applyPreferences($scope, $stretched) as $p) {
                if (isset($seen[$p['id'] ?? '']) || $out->contains(fn ($x) => ($x['id'] ?? null) === ($p['id'] ?? null))) {
                    continue;
                }
                $over = (float) $p['price'] - (float) $spec['max_price'];
                if ($over <= 0) {
                    continue;
                }
                $p['why'] = sprintf('GBP %s over budget, but in the area', number_format($over));
                $out->push($p);
            }
        }

        // A different property type, when they named one and stock is thin.
        if (! empty($spec['property_types']) && $onBrief->count() < 5) {
            $anyType = $spec;
            unset($anyType['property_types']);
            $scope = ($center && $radius)
                ? $this->withinRadius($properties, $center, $radius)
                : $properties;

            foreach ($this->applyPreferences($scope, $anyType) as $p) {
                if (isset($seen[$p['id'] ?? '']) || $out->contains(fn ($x) => ($x['id'] ?? null) === ($p['id'] ?? null))) {
                    continue;
                }
                $p['why'] = 'a ' . str_replace('_', ' ', \App\Support\PropertyClassifier::bucket($p))
                    . ' rather than what you asked for';
                $out->push($p);
            }
        }

        // Commission-paying first here too, and keep it short enough to scan.
        return $out->sortByDesc(fn ($p) => $this->paysCommission($p) ? 1 : 0)->take(10)->values();
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
