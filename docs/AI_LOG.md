# AI working log

Raw, running notes kept during the build (key prompts, pushback, AI mistakes and
how they were caught). AI_USAGE.md is the curated summary of this file.

Tool: Claude Code (CLI), model Claude Opus 5.5.

## Timeline

| Time (IST) | Phase | What happened |
|---|---|---|
| 09:40 | Planning | Gave Claude the brief, my proposed design and working rules. Claude read the PDF, checked Docker/gh/git identity, and returned a plan. |
| ~09:50 | Planning | Approved plan with all suggested changes; supplied personal GitHub account and commit email. |
| 09:55 | Building | Scaffolded Laravel 12 + Vite React-TS entirely via Docker (no local PHP/Composer). |
| 10:26 | Building | Full stack running via `docker compose up`; every acceptance criterion smoke-tested with curl, email seen in Mailpit. |
| ~10:28 | Testing | 29 API tests + 10 web tests. Deliberately broke code (mutation checks) to prove tests catch it: curated-first rule (3 tests failed), API error handling (3 failed). |
| ~10:31 | Reviewing | CI workflow added. Spawned an **independent reviewer agent** (fresh context, read-only, given the acceptance + assessment criteria) while docs were written. |
| ~10:33 | Reviewing | Triaged 14 findings: 1 false positive (docs not yet committed), 1 declined with reason, 12 fixed — each with a regression test, several verified end-to-end through the real proxy. |
| 10:36 | Reviewing | 37 API + 13 web tests green; README, DECISIONS, CLAUDE.md, PLAN written. |

## Key prompts

1. **Opening brief** – pasted the full challenge summary, my proposed design, and
   rules: "show me the plan and wait for my go-ahead", "challenge it if you
   disagree", "keep a running log of my prompts and AI mistakes", "explain the
   important decisions — I have to defend every line".
2. **Go-ahead** – "yes go ahead with all changes, use this email …" after reviewing
   the plan's seven pushbacks.
3. **Reviewer brief** (written by Claude for a fresh reviewer sub-agent — Claude
   chose to delegate the plan's "review pass" step so the review wouldn't share
   the builder's blind spots): read-only review of `git diff main...HEAD` against the five
   acceptance criteria and the five assessment areas; look specifically for
   correctness, security, React state, test blind spots and clean-machine run
   risks; output prioritised findings with file:line and a failure scenario.

## Where the AI pushed back on my design (accepted)

1. **"Top rated" meant two different things** in my design (curated on Home,
   API rating on Category). Now one definition in `TopRatedSelector`: curated
   first, topped up by rating.
2. **Search location** – moved from "server" (vague) to `ProductRepository::search()`
   so a future source can push search down.
3. **Don't leak Fake Store's shape** – domain `Product` + API Resources.
4. **Fixture data source** (`PRODUCT_SOURCE=fixture`) – protects the demo if
   Fake Store is down and proves the seam is real.
5. **Don't trust client-sent product details** in enquiries – only `product_id`.
6. **No database** – honest scope; enquiry persistence listed as next step.
7. `/api/v1` prefix and a CI workflow.

Claude also flagged that my global git email is my work address and that SSH
would push as my work account — set repo-local identity and push over HTTPS.

## AI mistakes and how they were caught

| # | Mistake | How it was caught | Fix |
|---|---|---|---|
| 1 | Scaffolded Laravel with the `composer:2` image (PHP 8.5) but wrote a `php:8.3` runtime image. The lock file pulled Symfony 8.1 (needs PHP ≥ 8.4.1). | `docker compose up --build` failed on `composer install`. | Runtime moved to `php:8.4-cli-alpine`. Lesson: pin the PHP version the lock file is resolved against. |
| 2 | First `CategoryController` read the category from `top_rated[0]` — crashes if `CATEGORY_TOP_RATED_COUNT=0`. | Self-review of the controller before running. | `TopRatedSelector::forCategory()` returns the category explicitly. |
| 3 | Planned to cache `Product` objects; a later change to the class (e.g. adding a field in the live interview) would collide with stale serialized objects. | Self-review while checking Laravel's cache serialization config. | Cache the raw upstream payload, map on read. |
| 4 | Docker healthcheck used `localhost`; busybox wget resolved it to IPv6 while `artisan serve` binds IPv4 → container "unhealthy", web never started. | `docker compose ps` showed `unhealthy`; `wget` inside the container got "connection refused". | Healthcheck uses `127.0.0.1`. |
| 5 | `PHP_CLI_SERVER_WORKERS` was set but silently ignored (needs `--no-reload`), so the API served one request at a time. | Warning in `docker compose logs api`. | Added `--no-reload`; documented that `.env` changes need a restart. |
| 6 | `.env.example` kept Laravel's `APP_DEBUG=true`: API 404s returned full stack traces with file paths. | curl smoke test of `/api/v1/products/999`. | Default `APP_DEBUG=false`. |

| 7 | `npm install -D vitest …` was run with `--silent`; it failed without output, so test deps were never installed (`package.json` had none). | `npm test` → "vitest: not found". | Reinstalled without `--silent`, checked `package.json`. Lesson: don't silence installs. |
| 8 | `vite.config.ts` used `process.env` with no Node types → `tsc -b` failed (Vite's own build doesn't typecheck config). | Ran the typecheck separately before committing. | Added `@types/node`. |
| 9 | Validation test claimed to check "errors next to the fields" but only checked the top alert. | Re-reading test names against assertions. | Added a field-level assertion (later: `aria-invalid`, accessible description, focus). |
| 10 | Wrote an inline FQCN in the service provider. | Pint in CI config (`pint --test`). | Pint auto-fix. |

### Found by the independent reviewer agent (and verified before fixing)

| # | Finding | Verified how | Fix |
|---|---|---|---|
| R1 | `email` rule accepts `jane(c)@example.com`, which Symfony Mime rejects when building the message → **500**. | Ran both validators side by side in the container for 4 addresses. | `email:rfc,strict` + test. |
| R2 | Plain-text email part used `{{ }}` → "men&#039;s clothing" in every enquiry for curated product 3. My HTML-escaping test only rendered the HTML part. | Read the view; test with `assertSeeInText`. | `{!! !!}` in the text view only. |
| R3 | **All users shared one rate-limit bucket**: behind the Vite proxy every request came from the web container IP. | Reasoned from config; then proved fix with two tests (real IP via trusted proxy; spoofing from untrusted IP still limited) and an end-to-end run (5×202 then 429). | Vite `xfwd`, `TRUSTED_PROXIES` (never `*`). |
| R4 | Search used substring match: "men" returned all women's clothing. | curl before/after. | Word-prefix regex + test. |
| R5 | Huge product id → `TypeError` 500. | Reviewer reproduced in container; test added. | Route constraint `[0-9]{1,9}`. |
| R6 | Empty/all-malformed upstream response cached for 10 min → every category 404. | Test. | Throw `ProductSourceUnavailable` on empty list. |
| R7 | Laravel default CORS `*` with port 8000 published. | curl with foreign `Origin`. | Closed by default, `CORS_ALLOWED_ORIGINS`. |
| R8 | First run could exceed healthcheck window on slow networks (bind mount hid image `vendor/`). | Reasoned from compose + entrypoint. | Anonymous `/app/vendor` volume + `start_period`. |
| R9–R11 | Stale search box on back nav; form errors not linked for screen readers; `/products/abc` leaked "route api/v1/products/NaN". | Tests written to fail first (mutation-checked the search box fix). | Keyed `SearchBar`; `aria-invalid`/`aria-describedby` + focus; client-side id guard. |
| R12 | `RateLimiter::clear('enquiries')` in tests was a no-op (wrong key). | Read framework code. | Removed; comment explains fresh array cache per test. |

Declined: auto-fallback to fixture data when Fake Store is down — it would hide
a real outage. Kept the explicit `PRODUCT_SOURCE=fixture` switch, documented in
README troubleshooting.

### My own slips while fixing review findings

| # | Mistake | How it was caught | Fix |
|---|---|---|---|
| 11 | First trusted-proxy implementation read `env()` inside `bootstrap/app.php` — returns null once `config:cache` runs in production, silently disabling it. | Self-review right after writing it. | Moved to `config/catalogue.php` + `TrustProxies::at()` in `AppServiceProvider`. |
| 12 | Wrote a "back navigation" test containing a `window.history.back()` call that did nothing (with a comment admitting it). | Re-read the test before committing. | Renamed to what it actually tests; removed dead line; mutation-checked it fails without the fix. |
| 13 | **Restored a mutated file with `git checkout --`, which also wiped the uncommitted fix in that file.** | My own post-check (`git diff` guard) printed a warning. | Re-applied the change, re-ran all checks. Lesson: back up to a temp file for mutation checks (as I did earlier), never restore from git with uncommitted work. |

### Found by the fresh-clone check

| # | Mistake | How it was caught | Fix |
|---|---|---|---|
| 14 | Deleted Laravel's example unit test, leaving `tests/Unit/` empty. Git doesn't track empty folders, so **on any clean checkout (and in CI) PHPUnit refused to run at all** ("Test directory not found"). Every local run passed because my working copy still had the empty folder. | Before pushing: fresh `git clone` → `docker compose build --no-cache` → `up` (71 s to healthy) → all five criteria via curl → both test suites. | Removed the empty Unit suite from `phpunit.xml`; re-verified on another fresh clone. |

Environment issue (not an AI mistake, but worth noting): Composer's parallel
downloads inside `php:8.4-cli-alpine` failed with TLS "bad record mac" errors on
my network. Reproduced in isolation with and without
`COMPOSER_MAX_PARALLEL_HTTP=1` before setting it in the Dockerfile.
