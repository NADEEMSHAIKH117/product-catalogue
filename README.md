# Product Catalogue PoC

A browsable, searchable product catalogue with product enquiries, built as a
foundation that can grow: the product data source, look and feel, and email
provider can each be swapped without touching the rest of the app.

- **Client:** React 19 + TypeScript (Vite) — `web/`
- **Backend:** PHP 8.4 + Laravel 12 — `api/`
- **Data source:** [Fake Store API](https://fakestoreapi.com), only ever called by the backend
- **Email:** any Laravel mail transport; [Mailpit](https://mailpit.axllent.org) locally as the "sales inbox"

Also see [DECISIONS.md](DECISIONS.md) (assumptions, trade-offs, what's next) and
[AI_USAGE.md](AI_USAGE.md) (how AI was used and where it went wrong).

## Quick start

Prerequisite: **Docker** (Docker Desktop or Docker Engine with Compose v2). Nothing else — no PHP, Composer or Node needed on the host.

```bash
git clone https://github.com/NADEEMSHAIKH117/product-catalogue.git
cd product-catalogue
docker compose up --build
```

The first run takes a few minutes (it downloads images and installs PHP and
Node dependencies). When the `web` container logs `VITE ... ready`, open:

| What | URL |
|---|---|
| **The app** | http://localhost:5173 |
| **Sales inbox** (Mailpit) | http://localhost:8025 |
| API (direct) | http://localhost:8000/api/v1/products/featured |

No configuration is required: on first start the API copies `api/.env.example`
to `api/.env` and generates an app key. No secrets, accounts or paid services
are needed.

Stop with `Ctrl+C`, or `docker compose down`.

### Walk through the acceptance criteria

1. **Home** — http://localhost:5173 shows the 5 curated top rated products.
2. **Category** — click a category in the header (e.g. *electronics*): a *Top rated* row, then *More in electronics*.
3. **Search** — type `jacket` and click **Search**: only matching products are shown.
4. **Product page** — click any product tile.
5. **Enquiry** — click **Enquire**, fill in your details, **Send enquiry** → "Thank you!". The email appears in Mailpit at http://localhost:8025.

## Running the tests

With the stack running:

```bash
docker compose exec api php artisan test     # Laravel feature tests
docker compose exec web npm test             # Vitest + Testing Library
```

Without the stack running, use `docker compose run --rm api php artisan test`
and `docker compose run --rm web npm test`.

Other checks (all run in CI, see `.github/workflows/ci.yml`):

```bash
docker compose exec api vendor/bin/pint --test   # PHP code style
docker compose exec web npx tsc -b               # TypeScript typecheck
docker compose exec web npm run lint             # ESLint
```

Tests never touch the network: backend tests fake Fake Store with `Http::fake`
(and `Http::preventStrayRequests()` fails any real call); frontend tests stub
`fetch` with a small fake of our API.

## Configuration

All configuration is environment variables in `api/.env` (created from
`api/.env.example`). After changing it, run `docker compose restart api`.

| Variable | Default | Purpose |
|---|---|---|
| `PRODUCT_SOURCE` | `fakestore` | `fakestore` (live API) or `fixture` (bundled snapshot, works offline) |
| `FEATURED_PRODUCT_IDS` | `3,5,11,12,18` | The owner's curated "top rated" products, in display order |
| `CATEGORY_TOP_RATED_COUNT` | `3` | Size of the "Top rated" row on category pages |
| `PRODUCT_CACHE_TTL` | `600` | Seconds to cache the Fake Store response |
| `FAKESTORE_BASE_URL`, `FAKESTORE_TIMEOUT` | Fake Store, `5` | Upstream location and timeout (seconds) |
| `SALES_INBOX_ADDRESS` | `sales@example.com` | Where enquiry emails are sent |
| `TRUSTED_PROXIES` | private Docker ranges | Proxies allowed to set `X-Forwarded-For` (rate limits use the real client IP) |
| `CORS_ALLOWED_ORIGINS` | *(empty)* | Extra browser origins allowed to call the API; empty = same-origin only |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, … | Mailpit SMTP | Email provider — switch to SES, Postmark, etc. with env vars only |

## Why this stack

- **Laravel** — I'm a PHP developer, so I can defend and extend it confidently.
  It also gives the pieces this brief needs out of the box: an HTTP client with
  retries and test fakes, a cache, form request validation, rate limiting,
  mailables with swappable transports, and a mature test harness.
- **React + TypeScript (Vite)** — typed API contract on the client, fast dev
  loop, and the most common choice for a web catalogue that will be re-skinned.
  One router dependency; no state library is needed at this size.
- **Docker Compose** — one command for reviewers, no host toolchain, and the
  same container approach carries over to AWS (ECS/App Runner).

## Architecture

```
 Browser ──► web (React, Vite) ──/api/v1──► api (Laravel) ──► ProductRepository ──► Fake Store API
                                              │                  ├─ FakeStoreProductRepository (HTTP + cache)
                                              │                  └─ FixtureProductRepository   (bundled JSON)
                                              └─► Mailable ──► MAIL_* transport ──► Mailpit (local) / SES (prod)
```

- **The browser never calls Fake Store.** Vite proxies `/api` to Laravel in
  development (same origin, no CORS); in production CloudFront would route
  `/api/*` to the API the same way.
- **One seam per thing that will change:** `ProductRepository` (data source),
  `styles/tokens.css` (look and feel), `MAIL_*` env vars (email provider).
- **Our own API contract.** Upstream data is mapped into a `Product` object and
  exposed through API Resources, so a new data source doesn't change the client.

### API

| Method | Path | Returns |
|---|---|---|
| GET | `/api/v1/products/featured` | Curated top rated products |
| GET | `/api/v1/categories` | `[{slug, name}]` |
| GET | `/api/v1/categories/{slug}/products` | `{category, top_rated[], others[]}` |
| GET | `/api/v1/products?search=term` | All products, or those where every word of `term` starts a word in the title, description or category |
| GET | `/api/v1/products/{id}` | One product (404 if unknown) |
| POST | `/api/v1/enquiries` | `202 {message}`; `422` validation errors; `429` rate limited; `503` mail failed |

Errors are always JSON `{message, errors?}`. If Fake Store is unreachable the
API returns `503` and the client shows a "try again" message.

### Layout

```
api/
  app/Catalogue/            Product, Category, ProductRepository contract, repositories, TopRatedSelector
  app/Http/                 Controllers (thin), API Resources, StoreEnquiryRequest
  app/Mail/                 ProductEnquiryMail (+ resources/views/emails)
  app/Providers/            CatalogueServiceProvider picks the data source
  config/catalogue.php      All catalogue settings
  resources/data/           Fixture snapshot of Fake Store products
  tests/Feature/            API tests
web/
  src/api/                  Typed API client — the only module that knows the backend URL
  src/pages/, components/   Home, Category, Search, Product, Enquiry
  src/styles/tokens.css     Design tokens
  src/test/                 Vitest + Testing Library tests
```

## Troubleshooting

- **Products don't load / "temporarily unavailable"** — Fake Store may be down
  or blocked on your network. Set `PRODUCT_SOURCE=fixture` in `api/.env` and
  `docker compose restart api`.
- **Port already in use** — something else is on 5173, 8000, 8025 or 1025; stop
  it or change the left-hand port in `docker-compose.yml`.
- **After changing `web/package.json`** — the container keeps its own
  `node_modules`; rebuild with `docker compose up --build -V web`.

## Time spent

See [AI_USAGE.md](AI_USAGE.md#time-spent).
