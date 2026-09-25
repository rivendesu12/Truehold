# Truehold — context for Claude

Property sourcing site for Truehold letting agents. Agents search rooms/flats,
check the source advert, and send clients links. Clients see a reduced view.

**Live:** https://truehold.yaenlinea.co (test subdomain).
`truehold.co.uk` still points at Ali's old server — needs registrar access.

Laravel 12, PHP 8.4, MySQL, nginx. Server `89.58.38.5`, app in `/var/www/truehold`,
branch `main`. SSH key `~/.ssh/truehold_deploy`.

## Never

- Commit `.env`, keys, service-account JSON or DB dumps.
- Run `migrate:fresh`/`migrate:refresh` against real data.
- `optimize:clear` on deploy — it wipes the **application cache**, where the
  feed, transport data and photo index live. Clear config/route/view only, then
  warm. Ignoring this caused a 27s page load.
- Do external network I/O in a web request. Crawls and Drive lookups are
  console-only; web reads cache and degrades.
- Cache a failure. Sheet services keep a last-good copy for 14 days.
- Put `APP_KEY` at risk — rotating it breaks encrypted data.

Secrets from Ali's original `.env` are compromised and still need rotating.

## Data sources

Ali's HarborOps feed is **off** (`HARBOROPS_ENABLED=false`, 23 Sep 2026): we
read every agency ourselves.

| Source | What |
|---|---|
| `supplier_sheet` | Room targets "Targets" tab — Banksia, AP. Plus AP workbook rooms it misses (`from_ap_workbook`) |
| `javier_sheet` | Javier's own "JAVIER VACANCY" ROOMS tab (JMS, FENIX); Room targets' Javier rows give way to it |
| `soreva_sheet` | Soreva's own sheet, read as **FORMULA** (hyperlinks hold photo folders); room links = room folders |
| `spareroom_direct` | We crawl ~25 SpareRoom advertisers: `suppliers.spareroom.advertisers` + `suppliers.feed_agencies` (the ones Ali's feed had). Each advertiser's `/u{id}` page lists all their adverts. `'active' => false` pauses one (Cloudrooms) — also hidden from wildcards |
| `whatsapp_sheet` | Its own sheet "Truehold - WhatsApp rooms (AI only)" (`1py4it…`, first tab; not in Room targets, Giaco never opens it), filled daily by a Cowork task from the Fausto / Vic / Fab groups (`docs/whatsapp-rooms-task.md`). Rules in `config/suppliers.php` whatsapp: Fausto no deposit, gone 4 days off the daily list; Vic 1 week / Fab 2 weeks deposit, gone 14 days unseen; `Let` = gone; pw → pcm; 4 rooms / 1 bath assumed |
| `spareroom` / `spreadsheet` | Ali's feed. Off. |

**Availability rules (Giaco):** AP/Horizon workbook — "Available" and not
booked; **ROLLING = someone lives there, not available**; TBC no. Javier —
status MOVE OUT or APT BREAK only; MOVE OUT/HOLD or blank = not available.
Tenant columns (names, phones, DOB, WhatsApp) are never read out; AP DOBs
become flatmate ages only.

All merged in `ScrapedListingsApiService::getAllProperties()` (cached, hourly).
Availability: "The advertiser is not currently accepting applications" = gone.
`ROLLING` and `APT BREAK` count as available.

## The principle that matters

Don't ask the AI to know London. Give the system real data; the model only maps
words onto it. Zones, lines, journey times, walk times come from TfL; regions
from postcode letters. The AI receives one sentence + area names and returns a
JSON filter spec — never listings, never results. ~£0.30/1000 searches.

`AgentSearchAssistant` parses; **our code** filters. Three result groups:
commission / no-commission / other options.

## Transport data

- `transport:build-stations` — 927 stations (incl. National Rail, bounded 30mi),
  zones + lines. **Aborts if any line fetch fails** rather than saving a partial
  index (a silent partial once lost the Victoria line).
- `transport:build-journeys` — real TfL journey times, station→hub, 22 hubs in
  `config/transport.php`. Resumable, incremental, ~15/min keyless.
  `TFL_APP_KEY` (optional) makes it 10× faster.
- Journey filter only engages above 90% coverage; below that it falls back to a
  straight-line radius and **says so**.

## Commands

```bash
php artisan audit:code       # calls to methods that don't exist
php artisan audit:data       # field coverage, unmapped data, filter correctness
php artisan audit:exposure   # what a client sees on a shared link
php artisan assistant:test   # real briefs through the parser (--only, --quick, --compare)
php artisan sigou:scenarios  # everything agents ask, end to end through the endpoint
php artisan assistant:insights  # how agents use Sigou
php artisan market:crawl     # refresh SpareRoom wildcards
php artisan suppliers:check  # what each direct supplier returns
php artisan commission:agencies
php artisan photos:warm
```

Scheduled (cron runs `schedule:run`): feed refresh hourly, `photos:warm` at :50,
availability every 6h, stations monthly, journeys weekly.

## Sigou (the assistant's mascot)

The assistant panel is "Ask Sigou", after the office admin: an animated SVG
(`partials/sigou.blade.php`) that blinks, follows the cursor, vapes his Lost
Mary while a search runs, rubs his hands on results. Agents only (`@auth`).

- He talks two ways. The model that parses the brief also writes `sigou`
  (first reaction / chit-chat answer), `sigou_found` and `sigou_none`; the
  panel shows the one matching the results. Persona is `PERSONA` in
  `AgentSearchAssistant`, drawn from his WhatsApp. Canned lines (greetings,
  the wait, fallbacks) are in `partials/sigou-lines.blade.php`.
- Each call gets two random running jokes (`jokesForThisOne`) so he does not
  repeat himself; girls about half the time. Guardrails: never about a real
  client/tenant/colleague, nothing sexual, never claims to know results.
  A joke only when it connects to what was typed (Giaco: out-of-context
  jokes are not funny): `RUNNING_JOKES` pairs each running joke with the
  words that set it up, and a call only gets the ones the message hooks.
  The wait lines follow the message too. What he knows about the office
  crew (persona notes, their running jokes, canned lines), learned from the
  team's WhatsApp groups, lives ONLY in `storage/app/private/sigou-office.json`
  on the server (`App\Support\SigouOffice`, git-ignored): never put
  colleagues' details in this public repo. "ask Giacomo" when something is
  missing. The Zoopla card says viewing ads only, never sourcing or the
  Truehold name.
- `chit_chat: true` answers without searching. A real brief read as chat
  fails the test.
- Results come in three bands: **best** (whole brief, actually in the area:
  own area field, nearest station ≤12 min walk, postcode district, or ≤½ mi
  for areas with no station; never the title), **other options** (nearby up
  to 2/4 mi, over budget, longer journey, relaxed — each with a `why`), and
  **wildcards** (below). Nothing widened ever lands in best.
- **Wildcards** = SpareRoom letting agents' free-to-contact rooms.
  `market:crawl` (nightly, polite, stops if refused; SpareRoom shows 1,000
  results per search, so SpareRoom's zones 1-3 search is split into rent
  bands, then single/double/flats; only new adverts are opened) →
  `market_listings` table; offered only in Sigou's search, opened on our own
  page `/market/{token}` (token is an HMAC, not the advert number). Guests see
  the room without agency/ref. Only adverts with a number are kept
  (`has_phone`: the "Call" contact method, `phoneadvertiser`); we record
  that one exists, never the digits (Giaco: not needed). `phone` column unused.
- Wildcard links (`/market/{token}`) render the normal `properties.show`
  page (map card, Share; agents also get the manager card and View Original
  Listing). No "wildcard" box (Giaco: looked bad).
- **Flatmates**: every result carries `household` ("3 flatmates · 2 females,
  1 male · aged 24 to 30 · professionals", `App\Support\Household`). From
  SpareRoom's "Current household" block, or the AP workbook (count + ages
  from DOB; names/phones never read out). "who lives in X" sets
  `property_lookup`, which narrows the search to that property.
- **Bank details**: "ap bank details", "how do I pay fenix". Six companies
  (AP Real Estate, Horizon Dreams, JMS, FENIX, Banksia, Soreva Management),
  each its own entry; groups ("ap horizon", "javier") show all members.
  Kept ONLY in `storage/app/private/agency-bank-details.json` on the server
  (git-ignored; the repo is public). Holding deposit forms ride along. The
  panel rains £ notes and Sigou does a money line.
- **Zoopla login**: "zoopla login / credentials / acc". Like the office
  WiFi, the model only sets `zoopla_login`; the email and password come from
  `ZOOPLA_LOGIN_EMAIL` / `ZOOPLA_LOGIN_PASSWORD` in the server `.env`, never
  the repo or the model. Agents only.
- `universal_credit` (UC / DSS / benefits) is a must-have: only a stated
  yes (`universal_credit_ok`; Kish via `suppliers.feed_agencies`).
- "no Cloudrooms" → `exclude_agencies`; `splitAgencies()` also moves any
  "no X" that lands in `agencies` (it once emptied a search).
- **Agencies** (`AgencyDirectory`): the "Agencies link" tab of Room targets,
  columns A-C and E-I only (D is never read; the "Agency rules" tab holds
  logins and is never touched). Link, max age, commission, agent share;
  refreshed hourly with the feed. "give me javier list" / "what does banksia
  pay" / "what does soreva have available".
- Commission: `config/commission.php` never_pay beats always_pay beats the
  agencies sheet beats the feed's `paying`. One rule (`CommissionRates::pays`)
  for cards, Sigou and the paying filter.
- Every question is logged to `assistant_interactions` (own DB, 180 days) with
  clicks per band; `assistant:insights` summarises, `assistant:usage` shows cost.
- End-to-end check: `sigou:scenarios` (~35 real calls through the endpoint, ~2p).
  Model reasoning is off (`OPENAI_REASONING_EFFORT=none`); the instructions are
  byte-identical per call so they cache (date and jokes ride in the user message).
- **The persona must not change the filters.** Check with
  `assistant:test --compare --sigou` (runs every brief with and without him,
  lists filter differences; `false` vs `null` on garden/parking/etc. is
  harmless, both mean "not asked"). Last run: 40/40 both ways.

## Client vs agent view

Agents share the same URL; the client opens it logged out. Hidden from guests:
agency/landlord name, source advert link, landlord portal, paying flag, the whole
Property Manager card. **Never sent to anyone:** `raw_row` (holds tenant names,
phones, WhatsApp). `occupation`/`pref_occupation` are normalised because a
mis-aligned scrape put a tenant's name there and it was served publicly.

`PropertyPayload` does this in one place — the map embeds listings as JSON, so
gating the Blade alone is not enough.

## Gotchas learned the hard way

- Filter correctness must be verified by checking results satisfy the filter.
  Counting results proves nothing — the en-suite filter returned plenty, all wrong.
- Room type and en-suite share one source field (`double|single|ensuite`), so
  "en-suite double" is unexpressible. Asking for both is unsatisfiable.
- Trust stated fields over prose. "one ensuite room in the house" describes a
  *different* room.
- Read all four `roomN_type` slots, not just the first.
- A filter over a column one source never fills silently drops that whole source.
  Preferences where silence usually means yes (students, furnished) keep the
  silent listings; must-haves (couples, smokers, pets, bills, garden, parking)
  still need a stated yes. The panel no longer shows the "we hold no data"
  box (Giaco: confusing, not wanted); `unanswerable` is still in the JSON.
- **A description is not a filter; a wish never removes rooms.** "She is a
  student" once cut 124 listings. Tenant descriptions set nothing; only needs
  a landlord can refuse on filter (couple, pet, smoker, move-by date). Wishes
  go in `nice_to_have`, which ranks. No stated date = available now.
  `assistant:test` has wording-trap cases with a `'none'` expectation.
- Cards show nearest station + walk, not postcode. A district is shown only when
  the listing's own postcode or text confirms it.
- Don't trust a district scraped from a shared page — SpareRoom pages include
  other adverts.

## Outstanding — needs Giaco

1. **Which supplier agencies pay commission?** `config/commission.php` →
   `always_pay`. Javier and AP unknown; Banksia/Soreva do. Mechanism built, empty.
2. **Commission rates** — intentionally blank; a paying agency shows as
   "COMMISSION" with no figure. `commission:agencies` prints the keys.
3. **Soreva** — new sheet (`1klNq5…`) has real links. Its agent tab now
   holds tenant name/phone/DOB/email in L-P; the reader stops at K.
4. **Smart Share** only came through Ali's dropped scrape; not in the
   Agencies link tab.
5. **Off-box backups** before real CRM data lands.
6. Restrict the Google Maps key to the domains.
7. truehold.co.uk DNS: not needed for now, truehold.yaenlinea.co is the site.

## Zoopla leads (Joy Homes)

`zoopla:leads` (every 5 min) reads "Tenant enquiry from X via Zoopla" emails
from the Joy Homes Zoho mailbox (hello@joyhomeslondon.co.uk) over IMAP and
appends them to the "Joy Homes - Zoopla leads" sheet, laid out like the old
"New Zoopla leads": **Sheet1** every enquiry, **To message** one row per new
phone, **Message template** the CRM import format. Read-only on the mailbox
(EXAMINE + BODY.PEEK, nothing marked read); looks back 3 days and dedupes
against the sheet, so a failed run is harmless. Rows are append-only.
`--dry-run` shows what it finds. Price and Zoopla's listing ref are only in
the HTML part; the plain part says 7bit but is quoted-printable.

## Availability check

With Ali's feed off, `properties:check-availability` has nothing to check.
Our own crawls drop an advert that is "not currently accepting
applications" when they read it (SpareRoom advertisers every 6h, wildcards
nightly); the sheets carry their own status.
