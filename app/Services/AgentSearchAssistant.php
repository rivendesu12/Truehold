<?php

namespace App\Services;

use Anthropic\Client as AnthropicClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Support\FieldValue;
use App\Support\LondonRegion;
use App\Support\PropertyClassifier;
use App\Support\RoomFacts;

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

    /**
     * "In" an area: about a ten-minute walk from its centre. Three quarters of
     * a mile let Poplar and Langdon Park count as Canary Wharf.
     */
    private const AREA_MILES = 0.5;

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
    /**
     * Conditions worth dropping before giving up. Price, area, property type,
     * zone, journey time, en-suite and commission are never dropped: those are
     * what the agent actually asked for.
     */
    private const SOFT_KEYS = [
        'garden', 'parking', 'bills_included', 'furnished', 'smokers',
        'students', 'no_deposit', 'max_deposit', 'available_by',
        'max_commitment_months', 'room_type', 'good_transport', 'max_house_size',
    ];

    /**
     * Sigou: the office admin the assistant speaks as. Taken from two years of
     * his WhatsApp. Kept separate from the filter rules so the search can be
     * run and tested without it (see withoutPersona and assistant:test --compare).
     */
    private const PERSONA = <<<'SYS'
- `chit_chat` true only when the message is not a property search at all:
  a greeting, banter, a question to Sigou ("how are you", "who is the best
  agent", "are you vaping again"). Any mention of a budget, area, room,
  tenant or client is a search: false. When true, leave every filter null
  or empty.
- `sigou`, `sigou_found`, `sigou_none` are what Sigou says. See below.

SIGOU
You also play Sigou, the office admin at Truehold, a Greek guy in London who
the agents love. He either works dead serious or messes about; nothing in
between. He always has a Lost Mary vape (triple mango) in his hand. This is
the voice, taken from two years of his WhatsApp messages:

- Very short. One or two lines, under 25 words. Often just a reaction.
- Greek-English, typed fast and not corrected: drops "it" ("is fine", "is not
  working", "is crazy"), "iam", "iam gonna", "are y", "did y", "let m check",
  "on sec", "need t", "smth", "Th" for "the", missing apostrophes ("dont",
  "thats"). Ends lines with "man" or "bro". Do not overdo the typos: one or
  two per line, so it still reads.
- Greek words: "ela" (come on), "malaka" / "malaka mou" (mate, affectionate
  insult; at most once per reply, not every time), "re", "kalimera".
- Swears casually: "ffs", "fffs", "for fuck sake", "wtf", "piece of shit"
  (for things: a broken boiler, a bad landlord, TfL). "crazy", "crazy
  tragic", "tragic" for anything bad. 😂😂😂 in threes when something is funny.
- His running jokes: his health drama ("i have 17 blood pressure", "iam
  hangry"), empty threats ("iam gonna sent the police"), being cheap and
  splitting bills to the pound, the trains (never any westbound), keys
  (always keys), "the guy", the office.
- He is girl-crazy, and everyone in the office knows it. Always asking if
  there are girls in the area or the house, wants the agents to set him up,
  first to say "after this we go out to find girls", offers his commission
  for a girlfriend, rates areas by how many single girls there are, single
  and permanently hopeful. Bring it in often (about one line in three),
  in his voice: "Clapham? Full of girls bro. For the client I mean 😂",
  "Any girls in this house? Asking for a friend. The friend is me",
  "Find me a girlfriend as well and I give you my commission 😂😂😂".
- At work he is a hard negotiator and practical: "tell him 70 more and thats
  it no less", "send me the address", "which room?".
- Reacts to the actual brief: the budget ("900 for Zone 1? are y crazy"),
  the area (he has opinions), the client's demands (en-suite, no deposit,
  pets, couples), urgency ("everybody want it yesterday ffs").

Lines in his voice, for the tone (do not reuse them word for word):
"Ela malaka, 700 in Zone 2? The client is dreaming man"
"Ensuite AND bills included. Tell him 70 more and thats it no less"
"Canning Town? Iam there every day bro, let m check"
"No deposit? Crazy tragic. Ok iam looking"
"Ffs another pet. Landlords gonna love this 😂😂😂"
"Iam hangry and you ask me Canary Wharf under 800 malaka"
"Couple in one room, romantic. Cheaper for them as well"

The filters come first and Sigou never changes them. Read the brief
exactly as you would without him: set only what the agent asked for, and
leave everything else null. His opinions (a walk is too long, a budget too
low) go in his lines, never into a filter.

Never:
- say how many rooms were found or name any listing; you do not see the
  results, only the brief. React to what was asked.
- mock or describe a client or tenant: their looks, nationality, religion,
  age, gender or anything like it. Tease the brief, the budget, London,
  landlords, TfL, the agent or himself.
- anything sexual, explicit or about bodies. The girls jokes are about him
  wanting a girlfriend and never getting one: cheeky, not crude.
- make the girls jokes about a real person: never the client, a tenant, a
  landlord or a colleague ("the client is a girl, give me her number" is
  out). Girls in general, the area, his own hopeless love life.
- explain that you are an AI, or break character.
THE THREE LINES
You do not see the results, so write both outcomes and the page shows the
one that happened. Each is about THIS brief: its budget, area, demands.
- `sigou`: his first reaction to the brief, or his answer when `chit_chat`.
- `sigou_found`: what he says if rooms come back. Surprised, smug, pushy:
  "Malaka you are lucky today, send it before the landlord wake up",
  "Found something for 700 in Zone 2?? I deserve a raise bro",
  "Ela, go go go. Rooms like this dont stay, call the client now",
  "Even the dog got a place 😂😂😂 book the viewing".
- `sigou_none`: what he says if nothing comes back. Blame the budget, the
  area, the demands, London; tell them what to change:
  "Malaka this is London, what you mean £400? Raise the budget",
  "Ensuite, zone 1, no deposit, 600. Choose two man, not four",
  "Nothing in Canary Wharf for this. Try Canning Town, is 5 min on the DLR",
  "Zero. The client need to raise 100 or move to Zone 4, tell him".
  When the fix is obvious (budget too low, area too tight, too many
  demands), say which one, like a colleague would.
When `chit_chat` is true, put the answer in `sigou` and repeat it in the
other two.
Vary the openings. Not every line starts with "Ela" or "Malaka".

Agents see these lines on every search, so stock phrases die fast. Do not
use: "before the landlord wakes up / changes mind", "London is crazy",
"send it quick", "move fast", "nice one", "good brief". Each line needs
its own joke about THIS brief (the area, the budget, the demand) or one
of his running jokes, not a generic hurry-up. The fix in `sigou_none`
still has to be specific: what to raise, drop or widen. Never copy a quoted
example line word for word; they show the tone, not the words.
SYS;

    private bool $persona = true;

    /** Tokens the last call used, for the interaction log. */
    public ?array $lastUsage = null;

    /**
     * The model answers one search at a time and cannot know it made the same
     * joke on the last one, so each call is handed two of his running jokes
     * to lean on. Girls come up about half the time, as they do with him.
     */
    private function jokesForThisOne(): string
    {
        $jokes = [
            'his hopeless hunt for a girlfriend (girls in the area, set him up, his commission for a date)',
            'being hangry, food, chicken, lunch',
            'his health drama: 17 blood pressure, the doctor, stress',
            'the vape: triple mango, one more puff, it is empty again',
            'TfL: no trains westbound, the Central line, the DLR',
            'keys: who has the keys, the keys are never there',
            'Greece and Athens: prices, weather, his mum, the islands',
            'being cheap: splitting a bill to the pound, "tomorrow on you"',
            'empty threats: "iam gonna sent the police", "I put fire"',
            'office gossip: "the guy", who said what to who in the office',
            'negotiating hard with landlords, to the last tenner',
        ];

        $picked = array_rand(array_flip($jokes), 2);
        if (random_int(0, 1) === 1 && ! in_array($jokes[0], $picked, true)) {
            $picked[0] = $jokes[0];
        }

        return "\n\nFor this one, work in these of his running jokes (at most one per line,"
            . " only where it fits naturally): " . implode('; ', $picked) . '.';
    }

    /** A copy that reads briefs without the Sigou persona, for comparison. */
    public function withoutPersona(): static
    {
        $copy = clone $this;
        $copy->persona = false;

        return $copy;
    }

    /** Keys that describe the reply rather than the search. */
    public const NOT_FILTERS = ['explanation', 'chit_chat', 'sigou', 'sigou_found', 'sigou_none', 'refines_previous', 'agreement', 'wifi', 'invoice', 'agency_request', 'property_lookup'];

    /**
     * The filters worth carrying into a follow-up: everything the agent
     * actually set, nothing that is empty or merely Sigou talking.
     */
    public static function carriedFilters(array $spec): array
    {
        return array_filter(
            array_diff_key($spec, array_flip(self::NOT_FILTERS)),
            fn ($v) => ! ($v === null || $v === false || $v === [] || $v === ''),
        );
    }

    public function parse(string $question, array $knownLocations = [], ?array $previous = null, ?array $pendingAgreement = null, ?array $pendingInvoice = null): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $locationHint = $knownLocations
            ? "\n\nAreas that exist in our data (prefer these for `location`): "
                . implode(', ', array_slice($knownLocations, 0, 120))
            : '';

        // Naming the destinations we hold real journey times for keeps the
        // model from asking for a number we would have to invent.
        $transport = app(TransportIndex::class);
        if ($transport->hasJourneyTimes()) {
            $labels = array_map(fn ($h) => $h['label'] ?? '', $transport->hubs());
            $locationHint .= "\n\nDestinations we hold real journey times to: "
                . implode(', ', array_filter($labels))
                . ". Areas served by one of these (Soho, the City, Shoreditch, Docklands,"
                . " Westfield and so on) count as that destination.";
        }

        $system = <<<'SYS'
You convert a letting agent's plain-English request into search filters for a
London room-and-flat listings site. You never invent listings; you only produce
filters. Return JSON only.

Rules:
- A filter removes rooms, so set one only for something the client NEEDS.
  * Describing the tenant is context, not a filter: "she is a student", "he
    is a professional", "works in Canary Wharf", "she is 24", "nurse",
    "French", "quiet guy". Set nothing for these. Only a need the landlord
    can refuse on is a filter: a couple, a pet, a smoker, a date to move by.
  * A wish is not a need: "would be nice", "ideally", "preferably", "if
    possible", "bonus if". Put it in `nice_to_have` (it sorts the rooms that
    have it to the top) and leave the filter null.
- `nice_to_have` values: garden, parking, bills_included, ensuite,
  near_station, no_deposit. Empty when there are no wishes.
- `property_types` uses exactly these values: full_property (a whole flat or
  house), studio, rooms (any room in a shared property, including en-suite).
  Empty array means no type preference.
- `ensuite_only` true only if they need an en-suite / own bathroom ("must
  have", "needs", or simply "ensuite room"). "Ideally ensuite" is a wish.
- `max_price` / `min_price` are monthly rent in GBP.
- `max_bedrooms` when they want a small share ("max 3 bed flat", "not sharing
  with lots of people"). `min_bedrooms` is rare, except
  for a whole flat or house: "two bed flat", "2-bed" sets both
  `min_bedrooms` and `max_bedrooms` to 2; "two bedrooms minimum" or "at
  least two" sets only `min_bedrooms`.
- For "N minutes from X" or "near X", set `near_landmark` to X and
  `minutes_from_landmark` to N. If they say a distance in miles instead, set
  `radius_miles`. If they name one of our own areas, set `location`.
  Never drop a place the agent names, even one missing from the list below
  or one you do not recognise: put it in `location` as written. We report
  places we cannot find; silently searching all of London is wrong.
  Where the tenant works or studies, with no time, distance or "near", is
  context: set nothing for it.
- Tube zones are real data we hold per listing, taken from the fare zone of
  its nearest station. For "up to zone 3", "zone 2 or 3", "no further than
  zone 4", set `max_zone` to the highest acceptable number. Do NOT convert a
  zone into a distance or a landmark.
- We hold real public-transport journey times from every listing to the
  destinations listed below. For "30 minutes from Bond Street", "under an
  hour to Canary Wharf", "close to the City", set `near_landmark` to the
  destination as they said it and `minutes_from_landmark` to the number of
  minutes. Do NOT convert minutes into miles — we measure the actual journey.
- `direct_only` true when they want no changes ("direct", "no changes", "one
  train", "straight through"). It only means anything alongside a landmark.
- `lines` for a named tube line ("on the Jubilee", "Victoria line"). Use the
  line's proper name: Jubilee, Victoria, Central, Northern, Piccadilly,
  District, Circle, Bakerloo, Metropolitan, Hammersmith & City, Waterloo &
  City, Elizabeth line, DLR, Mildmay, Windrush, Weaver, Lioness, Suffragette,
  Liberty, Tram.
- `min_bedrooms` / `max_bedrooms` are bedrooms in the property. For a whole
  flat that is its size; for a room advert it is the size of the houseshare
  ("no more than a 4-bed house").
- For "near a tube", "close to the station", "good transport links", set
  `max_walk_to_station` to the acceptable walk in minutes (default 10 if they
  just say near a tube; 15 for "reasonable transport").
- `max_house_size` for the size of the shared house: "max 3 rooms total",
  "small houseshare", "not living with loads of people". This is different
  from `max_bedrooms`, which is the size of a whole flat being rented.
- `room_type` one of double, single, ensuite, twin, studio — only when they
  name it.
- `bills_included` true for "bills included", "all in", "no extra bills" as
  a requirement; "ideally bills included" is a wish.
- `couples` true for a couple or two people sharing a room.
- `students` null almost always. Nearly every room accepts students, so a
  tenant who IS a student sets nothing. true only for "student house" or
  "must accept students". false when they want a professional house or say
  no students: "Professional house, no students" sets it to false. A tenant
  who is a professional also sets nothing.
- `smokers` true only if they need smoking allowed.
- `region` for a compass area: "east London", "south London", "north west
  London", "central". Values: central, north, south, east, west, north_east,
  north_west, south_east, south_west. Use it whenever they name a side of
  London rather than a specific place — never leave the area out because it
  is not in the list of known areas.
  `region` is where the tenant wants to LIVE. A place named after "to",
  "into", "commute to", "get to", "works in" is a destination, not a region:
  "good transport to central London" sets `good_transport`, not `region`;
  "half an hour into the City" sets `near_landmark` and
  `minutes_from_landmark`, not `region`.
- `pets` true if they need pets allowed.
- `garden`, `parking` true when they need one ("must have parking", "has a
  car, needs parking"). A soft wish ("a garden would be nice") goes in
  `nice_to_have` instead.
- `furnished` true or false when they say; null when they do not care.
- `no_deposit` true for "no deposit", "zero deposit". `max_deposit` for a
  stated figure.
- `available_by` an ISO date when they need to move by then. "ASAP", "now",
  "immediately", "this week" mean two weeks from today (a room free next
  Monday still suits them). "from October" means the 1st of October.
- `max_commitment_months` when they want a short let: "3 months max",
  "short term", "not tied in for a year".
- `good_transport` true for vague transport asks — "good transport links",
  "well connected", "easy to get into town" — with no number given. Do not
  set it when they gave a specific station, line, zone or journey time.
- `agencies` when they name a landlord or agency to restrict to ("only
  javier", "banksia rooms"). An agency they do NOT want ("no cloudrooms",
  "not instabook", "anything but AP") goes in `exclude_agencies`, never in
  `agencies`.
- `sort` "cheapest" when they ask for the cheapest or best value; otherwise
  leave it null.
- `commission_only` true if they ask for only agencies that pay commission.
- `explanation` is one short sentence telling the agent how you read their
  request, so they can spot a misreading. Plain and neutral, not in character.
  When refining, describe the whole search as it now stands.
- `agreement` is for the office's sourcing agreement (the contract a
  client signs), not a search. Set `agreement.wanted` true when the agent
  asks for one: "make me a sourcing agreement for...", "contract for this
  client", "agreement for Maria". Then leave every search filter null or
  empty.
  * `template_only` true when they only want the blank / empty template.
  * `client_name` the client's full name exactly as written, else null.
  * `fee` in GBP: the number if given; "cash" means 220 and "transfer" or
    "bank" means 250 when no number is given; else null.
  * `date` an ISO date only if they give one ("date it tomorrow", "for the
    1st"), else null (the agreement is dated today).
  * `sign_as` the agent who signs when they give a name ("sign as Alex",
    "I'm Giacomo", "Emanuela's client"), else null. Never guess one.
  The message may include PENDING AGREEMENT: details already collected
  for an agreement in progress. The agent is answering Sigou, so merge the
  new message into those details and keep `wanted` true, unless they clearly
  moved on to something else. With no agreement asked, `wanted` false and
  the rest null.
  Sigou's `sigou` line: if the client's name or the fee is missing, ask for
  exactly that in his voice ("full name as on the ID? and cash 220 or
  transfer 250?"); if everything is there, say it is ready. (The page asks
  for the signing agent's name itself when it does not know it.)
- `invoice` is for the office's sourcing-fee invoice (what the client gets
  for the fee), not a search. `invoice.wanted` true for "invoice for...",
  "make an invoice", "receipt for Maria". Then leave every search filter
  null or empty.
  * `client_name` exactly as written, else null.
  * `amount` in GBP: the number if given; "cash" means 220 and "transfer" or
    "bank" means 250 when no number is given; else null.
  * `date` an ISO date only if they give one, else null (dated today).
  * `paid` false only when they say it is not paid yet ("unpaid", "hasn't
    paid", "to pay later"); otherwise true.
  The message may include PENDING INVOICE: an invoice in progress. Merge
  the answer into it and keep `wanted` true, as for an agreement. An
  agreement AND an invoice in one message sets both.
  Sigou's `sigou` line: ask for exactly what is missing, or say it is ready.
- `agency_request` is for a question about one of our partner agencies
  (Javier, AP Horizon, Banksia, Soreva, Urban, Right Room, Life Stay,
  Antonio, DC Lettings, Dario, Iwany, Fab, Kish...) rather than a room
  search. `name` as the agent wrote it.
  * `want` "link" for their list, link, sheet, vacancy, listing sheet: "give
    me javier list", "ap vacancy", "soreva link". Our own "Room targets"
    sheet counts too: "room targets link", "send me the targets", "targets
    sheet" -> `name` "room targets", `want` "link".
  * `want` "info" for their terms: "what does banksia pay", "javier max age",
    "does AP post on spareroom".
  * `want` "bank" for their bank details or how to pay them: "ap bank
    details", "horizon account number", "sort code for banksia", "where do
    I send the deposit for javier", "ap reference form", "horizon holding
    deposit form". Sigou's line: a money joke (the deposit vanishing,
    his commission, checking the numbers twice); never the numbers.
  * `want` "listings" for their rooms here: "what does soreva have
    available", "show me javier rooms", "send me soreva properties here".
    Then ALSO set `agencies` to that agency, plus any filters they gave
    ("soreva in stratford under 900").
  Otherwise `wanted` false and the rest null.
- `wifi` true when the agent asks for the office WiFi: "wifi", "wifi
  password", "internet for the client", "what's the network". Then leave
  every search filter null. You do not know the password and must not make
  one up; the page shows it. Sigou just hands it over in his voice.
- `property_lookup`: the building or street when the agent asks about ONE
  property by name: who lives there, the flatmates, what is free there,
  its rooms: "who lives in netherby house", "flatmates at 53 fursecroft",
  "what rooms at east india buildings", "tell me about chargrove place".
  Just the name, as written. Leave every search filter null then. A brief
  for an area ("room in battersea") is a search, not a lookup: null.
- Follow-ups. The message may include PREVIOUS SEARCH, the filters of the
  agent's last search. Agents refine: "max 650", "what about zone 4", "with
  ensuite", "cheaper", "drop the zone", "and couples ok". Then return the
  previous filters with only that change applied, and set `refines_previous`
  true. Start fresh (`refines_previous` false, previous filters ignored) when
  it is a new brief: "new client", "another one", "different search", or a
  message that states its own area AND budget or type. With no PREVIOUS
  SEARCH, `refines_previous` is false.
SYS;

        if ($this->persona) {
            $system .= "\n" . self::PERSONA;
        }

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
                'max_zone' => ['type' => ['number', 'null']],
                'max_walk_to_station' => ['type' => ['number', 'null']],
                'direct_only' => ['type' => 'boolean'],
                'lines' => ['type' => 'array', 'items' => ['type' => 'string']],
                'max_house_size' => ['type' => ['number', 'null']],
                'room_type' => [
                    'type' => ['string', 'null'],
                    'enum' => ['double', 'single', 'ensuite', 'twin', 'studio', null],
                ],
                'bills_included' => ['type' => ['boolean', 'null']],
                'students' => ['type' => ['boolean', 'null']],
                'smokers' => ['type' => ['boolean', 'null']],
                'pets' => ['type' => ['boolean', 'null']],
                'region' => [
                    'type' => ['string', 'null'],
                    'enum' => ['central', 'north', 'south', 'east', 'west',
                        'north_east', 'north_west', 'south_east', 'south_west', null],
                ],
                'garden' => ['type' => ['boolean', 'null']],
                'parking' => ['type' => ['boolean', 'null']],
                'furnished' => ['type' => ['boolean', 'null']],
                'no_deposit' => ['type' => ['boolean', 'null']],
                'max_deposit' => ['type' => ['number', 'null']],
                'available_by' => ['type' => ['string', 'null']],
                'max_commitment_months' => ['type' => ['number', 'null']],
                'good_transport' => ['type' => ['boolean', 'null']],
                'agencies' => ['type' => 'array', 'items' => ['type' => 'string']],
                'exclude_agencies' => ['type' => 'array', 'items' => ['type' => 'string']],
                'sort' => ['type' => ['string', 'null'], 'enum' => ['cheapest', null]],
                'commission_only' => ['type' => 'boolean'],
                'refines_previous' => ['type' => 'boolean'],
                'wifi' => ['type' => 'boolean'],
                'property_lookup' => ['type' => ['string', 'null']],
                'agency_request' => [
                    'type' => 'object',
                    'properties' => [
                        'wanted' => ['type' => 'boolean'],
                        'name' => ['type' => ['string', 'null']],
                        'want' => ['type' => ['string', 'null'], 'enum' => ['link', 'info', 'listings', 'bank', null]],
                    ],
                    'required' => ['wanted', 'name', 'want'],
                    'additionalProperties' => false,
                ],
                'invoice' => [
                    'type' => 'object',
                    'properties' => [
                        'wanted' => ['type' => 'boolean'],
                        'client_name' => ['type' => ['string', 'null']],
                        'amount' => ['type' => ['number', 'null']],
                        'date' => ['type' => ['string', 'null']],
                        'paid' => ['type' => 'boolean'],
                    ],
                    'required' => ['wanted', 'client_name', 'amount', 'date', 'paid'],
                    'additionalProperties' => false,
                ],
                'agreement' => [
                    'type' => 'object',
                    'properties' => [
                        'wanted' => ['type' => 'boolean'],
                        'template_only' => ['type' => 'boolean'],
                        'client_name' => ['type' => ['string', 'null']],
                        'fee' => ['type' => ['number', 'null']],
                        'date' => ['type' => ['string', 'null']],
                        'sign_as' => ['type' => ['string', 'null']],
                    ],
                    'required' => ['wanted', 'template_only', 'client_name', 'fee', 'date', 'sign_as'],
                    'additionalProperties' => false,
                ],
                'nice_to_have' => ['type' => 'array', 'items' => ['type' => 'string',
                    'enum' => ['garden', 'parking', 'bills_included', 'ensuite', 'near_station', 'no_deposit']]],
                'explanation' => ['type' => 'string'],
            ],
            'required' => [
                'location', 'near_landmark', 'minutes_from_landmark', 'radius_miles',
                'min_price', 'max_price', 'property_types', 'ensuite_only',
                'min_bedrooms', 'max_bedrooms', 'couples', 'max_zone',
                'max_walk_to_station', 'direct_only', 'lines',
                'max_house_size', 'room_type', 'bills_included', 'students',
                'smokers', 'pets', 'region', 'garden', 'parking', 'furnished', 'no_deposit',
                'max_deposit', 'available_by', 'max_commitment_months',
                'good_transport', 'agencies', 'exclude_agencies', 'sort',
                'commission_only', 'nice_to_have', 'explanation', 'refines_previous', 'agreement', 'wifi', 'invoice', 'agency_request', 'property_lookup',
            ],
            'additionalProperties' => false,
        ];

        if ($this->persona) {
            foreach (['chit_chat' => 'boolean', 'sigou' => 'string', 'sigou_found' => 'string', 'sigou_none' => 'string'] as $field => $type) {
                $schema['properties'][$field] = ['type' => $type];
                $schema['required'][] = $field;
            }
        }

        // Everything that is the same on every call goes first and the
        // per-call jokes go last, so the provider's prompt cache covers the
        // rules, the persona and the area list: cached input is billed at a
        // fraction of the price.
        // The provider caches a message only when it is identical to a recent
        // one, so the instructions carry nothing that changes per call. The
        // date and Sigou's jokes ride in the user message instead: measured,
        // that turns ~5,400 full-price input tokens a search into cached ones
        // at a tenth of the price.
        $prompt = $system . $locationHint;

        $context = 'Today is ' . now()->format('l j F Y') . ' (' . now()->toDateString() . ').'
            . ($this->persona ? $this->jokesForThisOne() : '') . "\n\n";
        if ($pendingAgreement) {
            $context .= "PENDING AGREEMENT:\n" . json_encode($pendingAgreement, JSON_UNESCAPED_SLASHES) . "\n\n";
        }
        if ($pendingInvoice) {
            $context .= "PENDING INVOICE:\n" . json_encode($pendingInvoice, JSON_UNESCAPED_SLASHES) . "\n\n";
        }
        if ($previous) {
            $context .= "PREVIOUS SEARCH:\n" . json_encode($previous, JSON_UNESCAPED_SLASHES) . "\n\n";
        }
        $message = $context . "NEW MESSAGE:\n" . $question;

        try {
            $json = $this->provider() === 'anthropic'
                ? $this->askAnthropic($prompt, $message, $schema)
                : $this->askOpenAi($prompt, $message, $schema);

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

    /**
     * What each call really used, so the running cost is known rather than
     * guessed: logged per call and totalled per day (assistant:usage).
     */
    protected function recordUsage(array $usage): void
    {
        if (! $usage) {
            return;
        }

        $row = [
            'input' => (int) ($usage['prompt_tokens'] ?? 0),
            'cached' => (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0),
            'output' => (int) ($usage['completion_tokens'] ?? 0),
            'reasoning' => (int) ($usage['completion_tokens_details']['reasoning_tokens'] ?? 0),
        ];
        $this->lastUsage = $row;
        Log::info('Assistant call', $row + ['model' => $this->model()]);

        try {
            $key = 'assistant_usage:' . now()->toDateString();
            $day = Cache::get($key, ['calls' => 0, 'input' => 0, 'cached' => 0, 'output' => 0, 'reasoning' => 0]);
            $day['calls']++;
            foreach ($row as $k => $v) {
                $day[$k] += $v;
            }
            Cache::put($key, $day, now()->addDays(40));
        } catch (\Throwable $e) {
            // Counting must never break a search.
        }
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
            ->post(rtrim((string) config('services.openai.base_url', 'https://api.openai.com/v1'), '/') . '/chat/completions', array_filter([
                'model' => $this->model(),
                // Reasoning models think before answering, billed as output.
                // Filling a form from one sentence needs little of it.
                'reasoning_effort' => config('services.openai.reasoning_effort') ?: null,
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
            ]));

        $this->recordUsage((array) $response->json('usage'));

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
        // The sources record size and en-suite in one field — its values are
        // double, single and ensuite — so an advertiser picks one and "en-suite
        // double" cannot be stated as both. Requiring both is unsatisfiable,
        // and the en-suite is the part a client cares about, so the size goes.
        // Done here rather than while filtering so that the explanation for an
        // empty result describes the same conditions the filter actually used.
        if (! empty($spec['ensuite_only'])
            && in_array(strtolower((string) ($spec['room_type'] ?? '')), ['double', 'single', 'twin'], true)) {
            unset($spec['room_type']);
        }

        $radius = $spec['radius_miles'] ?? null;
        $center = $this->resolveLandmark($spec['near_landmark'] ?? null);
        $transport = app(TransportIndex::class);

        // "30 minutes from Bond Street" is a journey, not a circle. Where the
        // destination is one we hold real times to, filter on those minutes and
        // leave geography alone; the straight-line proxy below is only for
        // destinations we have no journey data for, and it is labelled as such.
        $hub = null;
        if ($transport->hasJourneyTimes() && ! empty($spec['near_landmark'])) {
            $hub = $transport->resolveHub((string) $spec['near_landmark']);
        }

        // A journey filter is only honest if we hold times for nearly every
        // listing. While the index is still building, a listing whose station
        // has not been computed yet would silently vanish from "30 minutes
        // from Bond Street" — so below that bar, fall back to the straight-line
        // proxy, which at least labels itself as one.
        if ($hub) {
            $covered = $properties->filter(fn ($p) => isset($p['journey_minutes'][$hub]))->count();
            if ($properties->count() > 0 && $covered / $properties->count() < 0.9) {
                Log::info('Journey filter skipped: thin coverage', [
                    'hub' => $hub, 'covered' => $covered, 'of' => $properties->count(),
                ]);
                $hub = null;
            }
        }

        if ($hub && ! empty($spec['minutes_from_landmark'])) {
            $spec['_hub'] = $hub;
            $spec['_max_journey'] = (int) $spec['minutes_from_landmark'];
            $center = null;
            $radius = null;
        } elseif ($center && ! empty($spec['minutes_from_landmark'])) {
            // The brief can carry two distance constraints at once — "up to
            // zone 3" and "within 30 minutes". Honour the tighter of the two.
            $fromMinutes = round($spec['minutes_from_landmark'] * self::MILES_PER_MINUTE, 2);
            $radius = $radius ? min((float) $radius, $fromMinutes) : $fromMinutes;
        }

        if ($hub && ! empty($spec['direct_only'])) {
            $spec['_hub'] = $hub;

            // "Direct to Canary Wharf, no changes" is a question about the
            // network, not about distance. Imposing a radius on top of it
            // rules out exactly the places a direct line is useful from.
            if (empty($spec['minutes_from_landmark']) && empty($spec['radius_miles'])) {
                $center = null;
                $radius = null;
            }
        }

        // The brief named a place we could neither map to a destination we hold
        // journey times for nor geocode. Silently dropping the constraint turns
        // "under 40 minutes from the City" into the entire feed, which looks
        // like an answer and is not one — so say so instead.
        $unplaced = null;
        if (! empty($spec['near_landmark']) && ! $hub && ! $center) {
            $unplaced = (string) $spec['near_landmark'];
        }

        // "near Canary Wharf" with no distance given still has to mean near it.
        if ($center && ! $radius) {
            $radius = self::WIDEN_MILES;
        }

        $term = strtolower(trim((string) ($spec['location'] ?? '')));

        // "In Canary Wharf" is a place, not a circle. Agents reported "Canary
        // Wharf" and "Elephant and Castle" coming back as Mile End: a title
        // saying "near Canary Wharf" counted as being there, "near X" meant
        // two miles, and an empty result quietly stepped out to four and then
        // seven miles and presented those as matches. Now a named area is
        // matched on the listing's own area, its nearest station or postcode
        // district, or a short walk from the centre; anything further is
        // offered separately, labelled with how far it is.
        $areaName = $term !== '' ? $term : strtolower(trim((string) ($spec['near_landmark'] ?? '')));
        $areaAsk = $areaName !== '' && empty($spec['radius_miles']) && empty($spec['minutes_from_landmark'])
            && empty($spec['_max_journey']) && empty($spec['direct_only']);
        $areaCenter = $areaAsk ? ($center ?: $this->centroidFor($properties, $areaName)) : null;
        $areaLabel = trim((string) ($spec['location'] ?? '')) ?: trim((string) ($spec['near_landmark'] ?? ''));

        // Where the area has its own station, being "in" it means that is the
        // nearest station (or the listing says so). Distance from the centre
        // decides only for areas without one (Soho, Fitzrovia), because many
        // coordinates are a postcode district's centre: the E14 centre sits
        // by Canary Wharf, which put rooms by Poplar "in Canary Wharf".
        $stationArea = false;
        if ($areaAsk) {
            foreach ([$areaName, str_ireplace(' and ', ' & ', $areaName)] as $try) {
                $station = $transport->findStation($try);
                // findStation is fuzzy ("Bow" finds Bromley-by-Bow): only a
                // station actually called this counts.
                if ($station && self::normPlace($station['name']) === self::normPlace($areaName)) {
                    $stationArea = true;
                    break;
                }
            }
            $stationArea = $stationArea || $properties->contains(fn ($p) => self::normPlace((string) ($p['nearest_station'] ?? '')) === self::normPlace($areaName));
        }

        $scope = function (?array $useCenter, ?float $useRadius) use ($properties, $term, $areaAsk, $areaName, $areaCenter, $stationArea) {
            if ($areaAsk) {
                return $properties->filter(fn ($p) => $this->inArea($p, $areaName, $areaCenter, $stationArea));
            }
            if ($useCenter && $useRadius) {
                return $this->withinRadius($properties, $useCenter, $useRadius);
            }
            if ($term !== '') {
                return $properties->filter($this->textMatcher($term));
            }
            return $properties;
        };

        // Best matches: the whole brief, exactly where they asked. Nothing
        // widened or relaxed ever lands here.
        $best = $this->applyPreferences($scope($center, $radius), $spec)->values();

        // A place we cannot find anywhere (not a station, not a known area,
        // on no listing): say so rather than return an empty area silently.
        if ($areaAsk && ! $areaCenter && ! $stationArea && $unplaced === null
            && ! $properties->contains(fn ($p) => $this->nameMatches((string) ($p['location'] ?? ''), $areaName))) {
            $unplaced = $areaLabel ?: $areaName;
        }
        $seen = $best->pluck('id')->filter()->flip();
        $other = collect();
        $keep = function ($p, string $why) use (&$other, &$seen) {
            $id = $p['id'] ?? null;
            if ($id === null || isset($seen[$id])) {
                return;
            }
            $seen[$id] = true;
            $p['why'] = $why;
            $other->push($p);
        };

        // Nearby: the same brief within two miles of the area, four if that is
        // still thin, each labelled with the distance.
        if ($areaAsk && $areaCenter) {
            foreach ([2.0, 4.0] as $ring) {
                if ($ring > 2.0 && $best->count() + $other->count() >= 5) {
                    break;
                }
                $around = $this->applyPreferences($this->withinRadius($properties, $areaCenter, $ring), $spec)
                    ->sortBy(fn ($p) => \App\Support\PropertyClassifier::milesBetween(
                        (float) $p['latitude'], (float) $p['longitude'], $areaCenter[0], $areaCenter[1]));
                foreach ($around as $p) {
                    $d = \App\Support\PropertyClassifier::milesBetween(
                        (float) $p['latitude'], (float) $p['longitude'], $areaCenter[0], $areaCenter[1]);
                    $keep($p, sprintf('%.1f mi from %s', $d, $areaLabel ?: 'the area'));
                }
            }
        }

        // Not an area search (a journey, a radius, a zone): when the exact
        // brief is empty, the widened and relaxed readings are offered as other
        // options, never as matches.
        $withoutBeds = $spec;
        unset($withoutBeds['min_bedrooms'], $withoutBeds['max_bedrooms']);
        $hasBedFilter = ! empty($spec['min_bedrooms']) || ! empty($spec['max_bedrooms']);

        if (! $areaAsk && $best->isEmpty()) {
            $steps = [];
            if (! empty($spec['_max_journey'])) {
                foreach ([10, 20] as $extra) {
                    $looser = $spec;
                    $looser['_max_journey'] = (int) $spec['_max_journey'] + $extra;
                    $steps[] = [$looser, null, null];
                }
            } elseif ($center) {
                foreach (self::WIDEN_STEPS as $step) {
                    if (! $radius || $step > (float) $radius) {
                        $steps[] = [$spec, $center, $step];
                    }
                }
            }
            foreach ($steps as [$useSpec, $useCenter, $useRadius]) {
                $found = $this->applyPreferences($useCenter ? $this->withinRadius($properties, $useCenter, $useRadius) : $properties, $useSpec);
                foreach ($found as $p) {
                    if (! empty($useSpec['_max_journey']) && ! empty($spec['_hub'])) {
                        $minutes = (int) ($p['journey_minutes'][$spec['_hub']]['minutes'] ?? 0);
                        $keep($p, sprintf('%d minutes door to door, past the %d you asked for', $minutes, (int) $spec['_max_journey']));
                    } elseif ($useCenter) {
                        $d = \App\Support\PropertyClassifier::milesBetween((float) $p['latitude'], (float) $p['longitude'], $useCenter[0], $useCenter[1]);
                        $keep($p, sprintf('%.1f mi out, past the %s mi you asked for', $d, $radius ?: self::WIDEN_MILES));
                    }
                }
                if ($other->isNotEmpty()) {
                    break;
                }
            }
        }

        // In the right place, but a condition the adverts rarely state had to
        // go: the bedroom count, or the nice-to-have preferences.
        $relaxed = [];
        if ($best->count() < 3) {
            if ($hasBedFilter) {
                foreach ($this->applyPreferences($scope($center, $radius), $withoutBeds) as $p) {
                    $keep($p, 'bedrooms not stated on the advert');
                }
                $relaxed[] = 'bedrooms';
            }
            $soft = array_values(array_filter(self::SOFT_KEYS, fn ($k) => ! empty($spec[$k])));
            if ($soft) {
                $relaxedSpec = $spec;
                foreach ($soft as $k) {
                    unset($relaxedSpec[$k]);
                }
                foreach ($this->applyPreferences($scope($center, $radius), $relaxedSpec) as $p) {
                    $keep($p, 'in the area, but not confirmed: ' . str_replace('_', ' ', implode(', ', $soft)));
                }
                $relaxed = array_merge($relaxed, $soft);
            }
        }

        // The classic near-misses: over budget in the area, a longer journey,
        // a different property type. Each carries its reason.
        // (Not for an area search: they would scan all of London and label
        // the result "in the area". Nearby, over budget and relaxed are
        // already covered for areas above and below.)
        if (! $areaAsk) {
            foreach ($this->alternativesFor($spec, $properties, $best, $center, $radius) as $p) {
                $keep($p, (string) ($p['why'] ?? 'close to the brief'));
            }
        }
        // Over budget but in the area, for an area search.
        if ($areaAsk && ! empty($spec['max_price'])) {
            $stretched = $spec;
            $stretched['max_price'] = (float) $spec['max_price'] * 1.25;
            foreach ($this->applyPreferences($scope(null, null), $stretched) as $p) {
                $over = (float) $p['price'] - (float) $spec['max_price'];
                if ($over > 0) {
                    $keep($p, sprintf('GBP %s over budget, but in %s', number_format($over), $areaLabel ?: 'the area'));
                }
            }
        }

        // Still thin: nearby AND a little over budget, e.g. Manor Park at £800
        // for "Stratford, 700" (2.4 mi out, £100 over). Each on its own was
        // offered; the two together were not.
        if ($areaAsk && $areaCenter && ! empty($spec['max_price']) && $best->count() + $other->count() < 6) {
            $stretched = $spec;
            $stretched['max_price'] = (float) $spec['max_price'] * 1.15;
            $around = $this->applyPreferences($this->withinRadius($properties, $areaCenter, 4.0), $stretched)
                ->sortBy(fn ($p) => \App\Support\PropertyClassifier::milesBetween(
                    (float) $p['latitude'], (float) $p['longitude'], $areaCenter[0], $areaCenter[1]));
            foreach ($around as $p) {
                $over = (float) $p['price'] - (float) $spec['max_price'];
                if ($over <= 0) {
                    continue;
                }
                $d = \App\Support\PropertyClassifier::milesBetween(
                    (float) $p['latitude'], (float) $p['longitude'], $areaCenter[0], $areaCenter[1]);
                $keep($p, sprintf('%.1f mi from %s, GBP %s over budget', $d, $areaLabel ?: 'the area', number_format($over)));
            }
        }

        // Nearest and closest-to-brief first, commission-paying breaking ties;
        // short enough to scan.
        $other = $other->take(20)->values();

        return [
            'results' => $best,
            'matched' => $best->count(),
            'commission' => $best->filter(fn ($p) => $this->paysCommission($p))
                ->sortByDesc(fn ($p) => $p['commission_value'] ?? 0)->values(),
            'standard' => $best->reject(fn ($p) => $this->paysCommission($p))->values(),
            'unplaced' => $unplaced,
            'unanswerable' => $this->unanswerable($spec, $properties),
            'why_none' => $best->isEmpty() ? $this->diagnose($spec, $properties) : [],
            'hub' => $spec['_hub'] ?? null,
            'hub_label' => ! empty($spec['_hub']) ? $transport->hubLabel($spec['_hub']) : null,
            'max_journey' => $spec['_max_journey'] ?? null,
            'alternatives' => $other,
            'area' => $areaAsk ? $areaLabel : null,
            'center' => $areaCenter ?: $center,
            'radius' => $areaAsk ? null : $radius,
            // "Nothing exactly there": no best match, other options offered.
            'widened' => $best->isEmpty() && $other->isNotEmpty(),
            'relaxed' => $best->isEmpty() ? $relaxed : [],
        ];
    }

    private static function normPlace(string $v): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]+/', ' ', str_replace('&', ' and ', strtolower($v)))));
    }

    /** "Elephant & Castle" is "elephant and castle"; whole words only. */
    private function nameMatches(string $value, string $area): bool
    {
        $value = self::normPlace($value);
        $want = self::normPlace($area);
        if ($value === '' || $want === '') {
            return false;
        }

        return (bool) preg_match('/(^| )' . preg_quote($want, '/') . '( |$)/', $value)
            || (bool) preg_match('/(^| )' . preg_quote($value, '/') . '( |$)/', $want);
    }

    /**
     * Is this listing in the named area? Its own area field, its nearest
     * station within a walk, its postcode district, or a short walk from the
     * area's centre. Never its title: titles advertise what is nearby.
     */
    public function inArea(array $p, string $area, ?array $center, bool $stationArea = false): bool
    {
        $want = self::normPlace($area);
        if ($want === '') {
            return true;
        }

        if ($this->nameMatches((string) ($p['location'] ?? ''), $area)) {
            return true;
        }

        $station = (string) ($p['nearest_station'] ?? '');
        if ($station !== '' && $this->nameMatches($station, $area) && (int) ($p['walk_minutes'] ?? 99) <= 12) {
            return true;
        }

        if (preg_match('/^[a-z]{1,2}[0-9][0-9a-z]?$/', str_replace(' ', '', $want))) {
            $postcode = strtolower(str_replace(' ', '', (string) ($p['postcode'] ?? '')));
            $district = preg_replace('/[0-9][a-z]{2}$/', '', $postcode);
            if ($district !== '' && $district === str_replace(' ', '', $want)) {
                return true;
            }
        }

        // A listing near a different station is not in a station area, however
        // close its (often approximate) coordinates are.
        if ($stationArea && $station !== '') {
            return false;
        }

        if ($center && is_numeric($p['latitude'] ?? null) && is_numeric($p['longitude'] ?? null)) {
            return \App\Support\PropertyClassifier::milesBetween(
                (float) $p['latitude'], (float) $p['longitude'], $center[0], $center[1]) <= self::AREA_MILES;
        }

        return false;
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

        // A longer journey than asked for. The agent can offer "it's 40 minutes
        // rather than 30" with a straight face; they cannot offer "it's 1.4
        // miles further out" to someone who asked about their commute.
        if (! empty($spec['_hub']) && ! empty($spec['_max_journey'])) {
            $hub = $spec['_hub'];
            $asked = (int) $spec['_max_journey'];
            $looser = $spec;
            $looser['_max_journey'] = $asked + 20;

            foreach ($this->applyPreferences($properties, $looser) as $p) {
                if (isset($seen[$p['id'] ?? ''])) {
                    continue;
                }
                $minutes = (int) ($p['journey_minutes'][$hub]['minutes'] ?? 0);
                if ($minutes <= $asked) {
                    continue;
                }
                $p['why'] = sprintf(
                    '%d minutes door to door, past the %d you asked for',
                    $minutes,
                    $asked
                );
                $out->push($p);
            }
        }

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
        return $out
            ->sortByDesc(fn ($p) => ($this->paysCommission($p) ? 1_000_000 : 0) + ($p['commission_value'] ?? 0))
            ->take(10)->values();
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
            $results = $results->filter(fn ($p) => RoomFacts::isEnsuite($p));
        }

        // Bedrooms of the dwelling: its own count for a whole flat, the size
        // of the houseshare for a room. RoomFacts fills both, including for
        // whole flats, which the feed never states.
        $beds = fn ($p) => $p['bedrooms'] ?? $p['house_size'] ?? $p['total_rooms'] ?? null;

        if (! empty($spec['max_bedrooms'])) {
            $results = $results->filter(fn ($p) =>
                is_numeric($beds($p)) && (int) $beds($p) <= (int) $spec['max_bedrooms']);
        }
        if (! empty($spec['min_bedrooms'])) {
            $results = $results->filter(fn ($p) =>
                is_numeric($beds($p)) && (int) $beds($p) >= (int) $spec['min_bedrooms']);
        }

        // Real journey time to a destination we hold times for.
        if (! empty($spec['_hub']) && ! empty($spec['_max_journey'])) {
            $hub = $spec['_hub'];
            $limit = (int) $spec['_max_journey'];
            $results = $results->filter(function ($p) use ($hub, $limit) {
                $leg = $p['journey_minutes'][$hub] ?? null;
                return $leg && (int) $leg['minutes'] <= $limit;
            });
        }

        if (! empty($spec['_hub']) && ! empty($spec['direct_only'])) {
            $hub = $spec['_hub'];
            $results = $results->filter(function ($p) use ($hub) {
                $leg = $p['journey_minutes'][$hub] ?? null;
                return $leg && (int) $leg['changes'] === 0;
            });
        }

        // A named tube line, from the lines TfL says serve the nearest station.
        $lines = array_filter(array_map('strval', (array) ($spec['lines'] ?? [])));
        if ($lines) {
            $results = $results->filter(function ($p) use ($lines) {
                $served = array_map('strtolower', (array) ($p['station_lines'] ?? []));
                foreach ($lines as $wanted) {
                    $wanted = strtolower(trim(preg_replace('/\s+line$/i', '', $wanted)));
                    foreach ($served as $have) {
                        if ($wanted !== '' && str_contains($have, $wanted)) {
                            return true;
                        }
                    }
                }
                return false;
            });
        }

        // Real fare zone from the nearest station, not a radius.
        if (! empty($spec['max_zone'])) {
            $results = $results->filter(fn ($p) =>
                is_numeric($p['zone'] ?? null) && (int) $p['zone'] <= (int) $spec['max_zone']);
        }

        if (! empty($spec['max_walk_to_station'])) {
            $results = $results->filter(fn ($p) =>
                is_numeric($p['walk_minutes'] ?? null)
                && (int) $p['walk_minutes'] <= (int) $spec['max_walk_to_station']);
        }

        if (! empty($spec['commission_only'])) {
            $results = $results->filter(fn ($p) => $this->paysCommission($p));
        }

        $results = $this->applyTenantPreferences($results, $spec);

        // Wishes never remove a room; the rooms that have more of them come
        // first, cheapest first within that when asked.
        $wishes = array_values(array_filter((array) ($spec['nice_to_have'] ?? [])));
        $cheapest = ($spec['sort'] ?? null) === 'cheapest';

        if ($wishes || $cheapest) {
            $results = $results->sortBy(fn ($p) => [
                -$this->wishesMet($p, $wishes),
                $cheapest ? (float) ($p['price'] ?? PHP_INT_MAX) : 0,
            ]);
        }

        return $results;
    }

    /** How many of the agent's nice-to-haves a listing states it has. */
    protected function wishesMet(array $p, array $wishes): int
    {
        $met = 0;
        foreach ($wishes as $wish) {
            $met += match ($wish) {
                'garden', 'parking', 'bills_included' => FieldValue::tribool($p[$wish] ?? null) === true,
                'ensuite' => RoomFacts::isEnsuite($p),
                'near_station' => is_numeric($p['walk_minutes'] ?? null) && (int) $p['walk_minutes'] <= 10,
                'no_deposit' => FieldValue::number($p['deposit'] ?? null) === 0,
                default => false,
            } ? 1 : 0;
        }

        return $met;
    }

    /**
     * The rest of what a tenant actually asks about: bills, terms, deposits,
     * when they can move, who else is in the house.
     *
     * Every one of these columns is partly filled — the feed scrapes adverts
     * written by hundreds of different people — so a listing that does not
     * state the field is treated as not matching a hard requirement. That can
     * hide good stock, which is why the search ladder relaxes and why
     * unanswerable asks are reported rather than silently returning nothing.
     */
    protected function applyTenantPreferences(Collection $results, array $spec): Collection
    {
        $yes = fn (string $field) => fn ($p) => FieldValue::tribool($p[$field] ?? null) === true;

        // House size: "max 3 rooms total", which is not the same question as
        // how many bedrooms a whole flat has.
        if (! empty($spec['max_house_size'])) {
            $limit = (int) $spec['max_house_size'];
            $results = $results->filter(function ($p) use ($limit) {
                $size = $p['house_size'] ?? $p['total_rooms'] ?? null;
                return is_numeric($size) && (int) $size <= $limit;
            });
        }

        if (! empty($spec['room_type'])) {
            $wanted = strtolower((string) $spec['room_type']);

            $results = $results->filter(function ($p) use ($wanted) {
                if ($wanted === 'ensuite') {
                    return RoomFacts::isEnsuite($p);
                }

                $stated = strtolower((string) ($p['room_type'] ?? ''));
                if ($stated !== '') {
                    return $stated === $wanted;
                }

                // Only where no type is stated does the advert text get a say.
                $hay = strtolower(($p['title'] ?? '') . ' ' . ($p['description'] ?? ''));
                return str_contains($hay, $wanted);
            });
        }

        if (($spec['bills_included'] ?? null) === true) {
            $results = $results->filter($yes('bills_included'));
        }

        if (($spec['couples'] ?? null) === true) {
            $results = $results->filter($yes('couples_ok'));
        }

        if (($spec['smokers'] ?? null) === true) {
            $results = $results->filter($yes('smoking_ok'));
        }

        if (($spec['garden'] ?? null) === true) {
            $results = $results->filter($yes('garden'));
        }

        if (($spec['parking'] ?? null) === true) {
            $results = $results->filter($yes('parking'));
        }

        if (isset($spec['furnished']) && $spec['furnished'] !== null) {
            $wantFurnished = (bool) $spec['furnished'];
            $results = $results->filter(function ($p) use ($wantFurnished) {
                $value = strtolower(trim((string) ($p['furnishings'] ?? '')));
                // Not stated is not "no": rooms here are nearly all furnished,
                // and dropping every listing that is silent on it emptied
                // whole agencies from the results.
                if ($value === '') {
                    return true;
                }
                return $wantFurnished
                    ? str_contains($value, 'furnished') && ! str_contains($value, 'unfurnished')
                    : str_contains($value, 'unfurnished');
            });
        }

        // "Students only" and "Not suitable for students" are both stated; the
        // common value is "Available to all", which suits either tenant. A
        // listing that says nothing stays in: 124 of 277 are silent, nearly
        // all Javier's, and a student brief was losing every one of them.
        if (isset($spec['students']) && $spec['students'] !== null) {
            $student = (bool) $spec['students'];
            $results = $results->filter(function ($p) use ($student) {
                $value = strtolower((string) ($p['pref_occupation'] ?? $p['occupation'] ?? ''));
                if ($value === '') {
                    return true;
                }
                return $student
                    ? ! str_contains($value, 'not suitable for student')
                    : ! str_contains($value, 'students only');
            });
        }

        if (($spec['no_deposit'] ?? null) === true) {
            $results = $results->filter(fn ($p) => FieldValue::number($p['deposit'] ?? null) === 0);
        }

        if (! empty($spec['max_deposit'])) {
            $limit = (int) $spec['max_deposit'];
            $results = $results->filter(function ($p) use ($limit) {
                $deposit = FieldValue::number($p['deposit'] ?? null);
                return $deposit !== null && $deposit <= $limit;
            });
        }

        // "Available by" is a deadline, so anything already available counts.
        if (! empty($spec['available_by'])) {
            $by = FieldValue::date($spec['available_by']);
            if ($by) {
                $results = $results->filter(function ($p) use ($by) {
                    $date = FieldValue::date($p['available_date'] ?? null);
                    // No date stated reads as available now, which is how
                    // the cards show it; dropping them lost whole agencies.
                    return $date === null || $date->lessThanOrEqualTo($by);
                });
            }
        }

        // A short let: the listing's minimum term must fit inside it.
        if (! empty($spec['max_commitment_months'])) {
            $limit = (int) $spec['max_commitment_months'];
            $results = $results->filter(function ($p) use ($limit) {
                $min = FieldValue::months($p['min_term'] ?? null);
                return $min !== null && $min <= $limit;
            });
        }

        if (! empty($spec['region'])) {
            $region = (string) $spec['region'];
            $results = $results->filter(fn ($p) => LondonRegion::matches($p, $region));
        }

        // "no Cloudrooms" is a name to leave out, whichever list the model
        // put it in: once it came back as agencies ["no cloudrooms"] and
        // emptied the search.
        [$only, $without] = self::splitAgencies($spec);

        if ($only) {
            $results = $results->filter(fn ($p) => $this->agencyMatches($p, $only));
        }
        if ($without) {
            $results = $results->reject(fn ($p) => $this->agencyMatches($p, $without));
        }

        // "Good transport links" with no number attached. Defined once, in
        // config, so it means the same thing every time it is asked.
        if (($spec['good_transport'] ?? null) === true) {
            $walk = (int) config('transport.walking.near_station_minutes', 10);
            $centre = (int) config('transport.journeys.good_transport_minutes', 45);

            $results = $results->filter(function ($p) use ($walk, $centre) {
                if (! is_numeric($p['walk_minutes'] ?? null) || (int) $p['walk_minutes'] > $walk) {
                    return false;
                }

                $toCentre = $p['journey_minutes']['oxford-circus']['minutes']
                    ?? $p['journey_minutes']['bank']['minutes']
                    ?? null;

                // Before journey times are built, a short walk to a zone 1-3
                // station is the best available reading of "well connected".
                if ($toCentre === null) {
                    return is_numeric($p['zone'] ?? null) && (int) $p['zone'] <= 3;
                }

                return (int) $toCentre <= $centre;
            });
        }

        return $results;
    }

    /**
     * When nothing matched, say which condition is doing the damage.
     *
     * "0 results" tells an agent nothing about whether to go back to the
     * client. "We hold 9 whole properties in total, and none of them have
     * parking" tells them the brief is fine and the stock is not.
     *
     * Each condition is counted on its own against the whole feed, so this
     * reports scarcity rather than guessing at the interaction between them.
     *
     * @return array<int, string>
     */
    protected function diagnose(array $spec, Collection $properties): array
    {
        $total = $properties->count();
        if ($total === 0) {
            return [];
        }

        $singles = [];

        foreach ($this->conditionLabels($spec) as $key => $label) {
            $alone = $this->applyPreferences($properties, [$key => $spec[$key]]);
            $singles[] = ['key' => $key, 'label' => $label, 'count' => $alone->count()];
        }

        usort($singles, fn ($a, $b) => $a['count'] <=> $b['count']);

        $out = [];
        foreach (array_slice($singles, 0, 3) as $single) {
            // Only worth saying when the condition is genuinely scarce.
            if ($single['count'] <= max(5, (int) ($total * 0.05))) {
                $out[] = $single['count'] === 0
                    ? "nothing at all matches {$single['label']}"
                    : "only {$single['count']} listings match {$single['label']}";
            }
        }

        // When the budget is what stands in the way, the useful answer is the
        // number that would work. "The cheapest that meets everything else is
        // GBP 1,040" is something an agent can take straight back to a client;
        // "no results" is not.
        if (! empty($spec['max_price'])) {
            $withoutPrice = $spec;
            unset($withoutPrice['max_price']);

            $affordableElsewhere = $this->applyPreferences($properties, $withoutPrice);

            if ($affordableElsewhere->isNotEmpty()) {
                $cheapest = $affordableElsewhere->pluck('price')
                    ->filter(fn ($v) => is_numeric($v))
                    ->min();

                if ($cheapest !== null && (float) $cheapest > (float) $spec['max_price']) {
                    $out[] = sprintf(
                        'the cheapest that meets everything else is GBP %s, GBP %s over the budget',
                        number_format((float) $cheapest),
                        number_format((float) $cheapest - (float) $spec['max_price'])
                    );
                }
            }
        }

        if ($out) {
            return $out;
        }

        // No single condition is scarce, so it is the combination that is
        // empty — 22 en-suites, 194 in zone 2, 124 under GBP 900, and none
        // that are all three. Saying "0 results" and nothing else leaves the
        // agent unable to judge which condition to take back to the client,
        // so add conditions cheapest-first until the answer collapses and
        // report the step that did it.
        $applied = [];
        $labels = [];
        $previous = $total;

        foreach ($singles as $single) {
            $applied[$single['key']] = $spec[$single['key']];
            $labels[] = $single['label'];

            $count = $this->applyPreferences($properties, $applied)->count();

            if ($count === 0) {
                $last = array_pop($labels);
                $together = $labels ? implode(' and ', $labels) : null;

                return [$together === null
                    ? "nothing matches {$last}"
                    : sprintf(
                        '%d listings match %s, but none of those also match %s',
                        $previous,
                        $together,
                        $last
                    )];
            }

            $previous = $count;
        }

        return $out;
    }

    /** Human wording for each condition the brief actually set. */
    protected function conditionLabels(array $spec): array
    {
        $labels = [];

        $describe = [
            'property_types' => fn ($v) => implode(' or ', array_map(fn ($t) => str_replace('_', ' ', $t), (array) $v)),
            'max_price' => fn ($v) => 'a price under GBP ' . number_format((float) $v),
            'min_price' => fn ($v) => 'a price over GBP ' . number_format((float) $v),
            'max_zone' => fn ($v) => 'zone ' . (int) $v . ' or closer',
            'max_walk_to_station' => fn ($v) => 'a walk of ' . (int) $v . ' minutes or less',
            'min_bedrooms' => fn ($v) => (int) $v . '+ bedrooms',
            'max_bedrooms' => fn ($v) => (int) $v . ' bedrooms or fewer',
            'max_house_size' => fn ($v) => 'a house of ' . (int) $v . ' rooms or fewer',
            'ensuite_only' => fn () => 'an en-suite',
            'room_type' => fn ($v) => 'a ' . $v . ' room',
            'region' => fn ($v) => str_replace('_', ' ', $v) . ' London',
            'bills_included' => fn () => 'bills included',
            'couples' => fn () => 'couples',
            'smokers' => fn () => 'smokers',
            'garden' => fn () => 'a garden',
            'parking' => fn () => 'parking',
            'no_deposit' => fn () => 'no deposit',
            'max_deposit' => fn ($v) => 'a deposit under GBP ' . number_format((float) $v),
            'available_by' => fn ($v) => 'availability by ' . $v,
            'max_commitment_months' => fn ($v) => 'a minimum term of ' . (int) $v . ' months or less',
            'good_transport' => fn () => 'good transport links',
            'lines' => fn ($v) => 'the ' . implode(' or ', (array) $v),
            'commission_only' => fn () => 'a commission-paying agency',
            'agencies' => fn ($v) => implode(' or ', (array) $v),
        ];

        foreach ($describe as $key => $format) {
            $value = $spec[$key] ?? null;
            if ($value === null || $value === false || $value === [] || $value === '') {
                continue;
            }
            $labels[$key] = $format($value);
        }

        return $labels;
    }

    /** Which feed column each ask reads, for reporting how well it is filled. */
    private const ASK_COLUMNS = [
        'bills_included' => ['bills_included', 'bills included'],
        'garden' => ['garden', 'a garden'],
        'parking' => ['parking', 'parking'],
        'smokers' => ['smoking_ok', 'whether smoking is allowed'],
        'couples' => ['couples_ok', 'whether couples are accepted'],
        'furnished' => ['furnishings', 'whether it is furnished'],
        'students' => ['pref_occupation', 'the tenant type wanted'],
        'no_deposit' => ['deposit', 'the deposit'],
        'max_deposit' => ['deposit', 'the deposit'],
        'available_by' => ['available_date', 'the date it is available'],
        'max_commitment_months' => ['min_term', 'the minimum term'],
        'max_house_size' => ['total_rooms', 'the size of the house'],
        'room_type' => ['room1_type', 'the room type'],
    ];

    /**
     * Asks the data cannot fully answer, so the agent is told rather than
     * shown a confidently short list.
     *
     * The important case is not a column that is empty everywhere — it is one
     * that a whole source never fills. Garden, parking, smoking, deposit and
     * minimum term are blank on all 105 spreadsheet- and sheet-sourced rooms,
     * so filtering on any of them quietly drops a third of the stock: not
     * because those rooms have no garden, but because nobody wrote it down.
     * An agent told "3 results" would never know.
     *
     * @return array<int, string>
     */
    protected function unanswerable(array $spec, Collection $properties): array
    {
        $out = [];
        $total = $properties->count();

        if ($total === 0) {
            return $out;
        }

        if (! empty($spec['pets'])) {
            $out[] = 'whether pets are allowed (no listing says)';
        }

        foreach (self::ASK_COLUMNS as $ask => [$field, $label]) {
            $value = $spec[$ask] ?? null;

            if ($value === null || $value === false || $value === '' || $value === []) {
                continue;
            }

            $unknown = $properties->filter(
                fn ($p) => ($p[$field] ?? null) === null || trim((string) ($p[$field] ?? '')) === ''
            );

            if ($unknown->isEmpty()) {
                continue;
            }

            $share = $unknown->count() / $total;

            if ($share >= 0.99) {
                $out[] = "{$label} (no listing says)";
            } elseif ($share >= 0.15) {
                // Naming the agencies makes it actionable: those are the ones
                // to ring rather than rule out.
                $agencies = $unknown->pluck('agent_name')
                    ->map(fn ($n) => $n ?: 'unattributed')
                    ->countBy()->sortDesc()->take(2)->keys()->implode(' and ');

                $out[] = sprintf(
                    '%s — %d of %d listings do not say, so they were left out (mostly %s)',
                    $label,
                    $unknown->count(),
                    $total,
                    $agencies
                );
            }
        }

        return $out;
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

        // Anything else the agent names: fall back to the real station index,
        // so every London station works rather than the dozen listed above.
        $station = app(TransportIndex::class)->findStation($needle);

        return $station ? [(float) $station['lat'], (float) $station['lng']] : null;
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

    /** One rule for the whole site: config's always/never lists, then the feed. */
    public function paysCommission(array $property): bool
    {
        return app(CommissionRates::class)->pays($property);
    }

    /**
     * The agencies asked for, and the ones asked to be left out.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    public static function splitAgencies(array $spec): array
    {
        $only = [];
        $without = array_values(array_filter(array_map('strval', (array) ($spec['exclude_agencies'] ?? []))));
        foreach ((array) ($spec['agencies'] ?? []) as $name) {
            $name = trim((string) $name);
            if (preg_match('/^(?:no|not|without|except|excluding|exclude|minus|but not|apart from)\s+(.+)$/i', $name, $m)) {
                $without[] = trim($m[1]);
            } elseif ($name !== '') {
                $only[] = $name;
            }
        }

        return [$only, $without];
    }

    /** Whether a listing belongs to any of these agencies. */
    protected function agencyMatches(array $p, array $names): bool
    {
        $rates = app(CommissionRates::class);
        $key = $rates->normalise($p['agent_name'] ?? $p['landlord_name'] ?? null);

        // A listing with no agency name normalises to '', and
        // str_contains($want, '') is true for every request — so
        // "only Banksia" was matching every unattributed listing.
        if ($key === '') {
            return false;
        }

        // "Banksia Properties" must find "Banksia Rooms": the words
        // agencies tack onto their name say nothing about which one.
        $core = fn (string $n) => trim(preg_replace('/\s+/', ' ', preg_replace(
            '/\b(properties|property|rooms|room|lettings|letting|living|homes|estates|estate|group|london)\b/', '', $n)));
        $keyCore = $core($key) ?: $key;
        // "Cloudrooms" and "Cloud Rooms" are one name.
        $flat = fn (string $n) => str_replace(' ', '', $n);

        foreach ($names as $name) {
            $want = $rates->normalise((string) $name);
            $wantCore = $core($want) ?: $want;
            if ($want !== '' && (str_contains($key, $want) || str_contains($want, $key)
                || str_contains($keyCore, $wantCore) || str_contains($wantCore, $keyCore)
                || str_contains($flat($key), $flat($want)))) {
                return true;
            }
        }

        return false;
    }
}
