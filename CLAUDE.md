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

## Data sources (~277 listings)

| Source | What |
|---|---|
| `spareroom` | HarborOps scraped-listings API (Ali's feed) |
| `spreadsheet` | Ali's scrape of Javier/Smart Share sheets; agency in `raw_row.Company` (JMS+FENIX = Javier) |
| `supplier_sheet` | "Targets" tab — Banksia, AP, Javier. Fixed column indices |
| `soreva_sheet` | Soreva's own sheet, read as **FORMULA** (hyperlinks hold photo folders) |
| `spareroom_direct` | We crawl DC Lettings, Antonio, Life Stay (2 accounts) from seed adverts |

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
php artisan assistant:test   # 34 real agent briefs
php artisan suppliers:check  # what each direct supplier returns
php artisan commission:agencies
php artisan photos:warm
```

Scheduled (cron runs `schedule:run`): feed refresh hourly, `photos:warm` at :50,
availability every 6h, stations monthly, journeys weekly.

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
  The assistant now reports the shortfall and names the agencies.
- Cards show nearest station + walk, not postcode. A district is shown only when
  the listing's own postcode or text confirms it.
- Don't trust a district scraped from a shared page — SpareRoom pages include
  other adverts.

## Outstanding — needs Giaco

1. **Which supplier agencies pay commission?** `config/commission.php` →
   `always_pay`. Javier and AP unknown; Banksia/Soreva do. Mechanism built, empty.
2. **Commission rates** — intentionally blank; a paying agency shows as
   "COMMISSION" with no figure. `commission:agencies` prints the keys.
3. **Soreva photos** — their sheet's hyperlink URLs are the literal text
   `[link removed]`. Need a version with working links.
4. **Javier photos** — 13 rows have no folder link in the sheet.
5. **truehold.co.uk DNS** → `89.58.38.5`, then certbot.
6. **Off-box backups** before real CRM data lands.
7. Restrict the Google Maps key to the domains.
8. Ask Andrei/Ali: why 279 feed rows have no URL and 198 have `url = "Pictures"`.

## Not verified

`properties:check-availability` has not been watched end-to-end since the
September 2026 changes. It runs unattended every 6h and **hides listings**.
Worth one supervised run.
