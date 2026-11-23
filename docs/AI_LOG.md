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

## Key prompts

1. **Opening brief** – pasted the full challenge summary, my proposed design, and
   rules: "show me the plan and wait for my go-ahead", "challenge it if you
   disagree", "keep a running log of my prompts and AI mistakes", "explain the
   important decisions — I have to defend every line".
2. **Go-ahead** – "yes go ahead with all changes, use this email …" after reviewing
   the plan's seven pushbacks.

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

Environment issue (not an AI mistake, but worth noting): Composer's parallel
downloads inside `php:8.4-cli-alpine` failed with TLS "bad record mac" errors on
my network. Reproduced in isolation with and without
`COMPOSER_MAX_PARALLEL_HTTP=1` before setting it in the Dockerfile.
