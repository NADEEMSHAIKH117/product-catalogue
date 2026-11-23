# Decisions and assumptions

## Assumptions where the acceptance criteria were unclear

| # | Question | Assumption | Why |
|---|---|---|---|
| 1 | What does "top rated" mean? | **Curated by the owner**: an ordered list of product IDs (`FEATURED_PRODUCT_IDS`). | The home criterion says "GIVEN *I have chosen* 5 products". Customer ratings from Fake Store are fake and not under the owner's control. |
| 2 | "Top rated products for that category" | The **same** definition, applied per category: curated products in the category first (in the owner's order), then **topped up by customer rating** to `CATEGORY_TOP_RATED_COUNT` (3). Everything else appears below as "More in …" in source order. | One meaning of "top rated" across the app (implemented once, in `TopRatedSelector`). The top-up means a category the owner hasn't curated still has a sensible top rated row. |
| 3 | What if a curated ID no longer exists? | Skip it and log a warning; the home page shows the rest. | A stale config value shouldn't take the home page down. |
| 4 | What does "text matching the search term" mean? | Case-insensitive; **every word** of the term must match the **start of a word** in the title, description or category. | "mens jacket" finds "Mens Cotton Jacket" (a whole-phrase match wouldn't); "jack" finds jackets; "men" does *not* match "women" (a plain substring match did — caught in review). |
| 5 | When does search run? | On submit (Search button or Enter), not as you type. Blank terms are ignored; max 100 characters. The term lives in the URL (`/search?q=`). | Matches the criterion ("WHEN I click the search button"); the URL makes results shareable and the back button work. |
| 6 | Which contact details? | Name and email required; phone and message optional. | Enough for sales to reply; fewer required fields mean more enquiries. |
| 7 | What is "the sales inbox"? | A configurable address (`SALES_INBOX_ADDRESS`) reached through Laravel's mailer; Mailpit locally. The email's Reply-To is the customer. | Lets sales answer straight from their inbox; the provider is swappable by env vars. |
| 8 | When is "Thank you" shown? | Only after the mail server has accepted the email. If sending fails, the customer sees a "please try again" message and the form stays filled in. | Showing "Thank you" for an enquiry nobody received would silently lose a lead. |
| 9 | Currency | Displayed as AUD. | Fake Store doesn't specify one; the audience is Australian. Formatting is in one helper (`web/src/lib/format.ts`). |
| 10 | Product images | The backend passes Fake Store's image URLs through; the browser loads the image files from Fake Store's host. | The constraint is about product *data*, which all comes through our API. Images are static assets referenced by URL; with a real data source they'd come from our own CDN. |
| 11 | Users and auth | None. The catalogue and enquiry form are public. | Not in the criteria; enquiries are protected by validation and rate limiting instead. |

## Requirements dropped or simplified, and why

- **No database. Enquiries are not stored; the email is the only record.** This
  keeps the stack to three containers and nothing to migrate. Trade-off: if the
  mail server is down, the customer is told to retry rather than the lead being
  queued. This is the first thing I'd change (see below).
- **Email is sent synchronously (no queue).** It goes with the point above:
  without stored enquiries, a queued email that fails is lost silently. With a
  database, it becomes *save → queue → retry*.
- **Curating "top rated" is an env var, not an admin screen.** It's enough to
  demo the behaviour, and the `TopRatedSelector` doesn't care where the IDs
  come from.
- **No pagination or filtering.** Fake Store has 20 products. The repository
  interface is where paging would be added.
- **Minimal styling.** The look and feel is being designed separately; I
  invested in design tokens instead of visual polish.
- **No end-to-end (browser) tests.** Critical paths are covered by API feature
  tests and frontend integration tests against a fake API. Playwright against
  the compose stack is next.
- **Development containers only.** `artisan serve` and the Vite dev server are
  fine for a PoC, not for production (see AWS notes below).
- **Rate limiting is per client IP only** (5 enquiries/minute, 120 API calls/minute).
  `X-Forwarded-For` is trusted only from `TRUSTED_PROXIES` (locally the private
  Docker ranges; in production the load balancer's subnet), so clients can't
  spoof their way around the limit. No CAPTCHA or honeypot yet.
- **CORS is closed by default.** The client is same-origin (Vite proxy locally,
  CloudFront path routing in production), so no other origin needs browser
  access; `CORS_ALLOWED_ORIGINS` opens it deliberately if ever needed.

## How the design grows beyond the PoC

The three things the brief says will change each have one seam:

| Expected change | Where it happens | What stays untouched |
|---|---|---|
| **Product data source** | Implement `App\Catalogue\Contracts\ProductRepository` (e.g. a database, PIM or commerce platform) and add a case in `CatalogueServiceProvider`. Sources with native search/paging implement those methods directly rather than extending `InMemoryProductRepository`. | Controllers, API contract, `TopRatedSelector`, the whole client. Proven by the existing `fixture` source. |
| **Look and feel** | `web/src/styles/tokens.css` (colours, type, spacing), then component CSS. | Data fetching, routing and API client. |
| **Email provider** | `MAIL_MAILER` / `MAIL_*` env vars (SES, Postmark, Mailgun, SMTP). | All code. |

Other growth paths already prepared for:

- **Versioned API** (`/api/v1`) so the client and API can evolve separately.
- **Our own response contract** (API Resources), decoupled from any upstream shape.
- **Upstream resilience:** timeout, 2 retries, caching of successful responses
  only, malformed records dropped, a clean `503` to the client.
- **Config in one place** (`config/catalogue.php`), all env-driven.
- **CI from day one** (Pint, PHPUnit, tsc, ESLint, Vitest, build).

### Path to AWS

- **Web:** `npm run build` → S3 + CloudFront.
- **API:** php-fpm + nginx (or Laravel Octane) container on ECS Fargate or App
  Runner behind an ALB. CloudFront routes `/api/*` to it, so the browser still
  sees a single origin.
- **Cache and rate limits:** ElastiCache (Redis), shared across API tasks.
  The file cache only works for a single container.
- **Email:** Amazon SES via `MAIL_MAILER=ses`.
- **Config/secrets:** SSM Parameter Store / Secrets Manager injected as env vars.
- **Logs:** stdout → CloudWatch.

## What I'd do next with another day

1. **Store enquiries and queue the email** (RDS or DynamoDB + SQS): no lost leads,
   automatic retries, and a record for reporting or a CRM sync.
2. **Playwright end-to-end tests** of the five criteria against the compose
   stack, run in CI.
3. **Serve the last good catalogue when Fake Store is down** (keep a stale copy
   alongside the fresh cache) instead of returning 503.
4. **Spam protection** on the enquiry form (honeypot or Cloudflare Turnstile).
5. **Production images and infrastructure as code** (CDK or Terraform) for the
   AWS layout above.
6. **An OpenAPI spec** with generated TypeScript types, so the client contract
   can't drift from the API.
7. **Observability:** structured JSON logs with request IDs, plus upstream
   latency/error metrics.
