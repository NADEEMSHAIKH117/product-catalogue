# CLAUDE.md

Instructions for AI coding agents working in this repository.

## Project

Product catalogue PoC: Laravel 12 API (`api/`) + React/TypeScript Vite client
(`web/`), run with Docker Compose. Product data comes from the Fake Store API
through the backend only. Read `README.md` and `DECISIONS.md` before changing
behaviour; record new assumptions or trade-offs in `DECISIONS.md`.

## Commands (everything runs in Docker — PHP/Composer are not installed on the host)

```bash
docker compose up --build                     # app :5173, API :8000, Mailpit :8025
docker compose exec api php artisan test      # backend tests
docker compose exec api vendor/bin/pint       # format PHP
docker compose exec web npm test              # frontend tests
docker compose exec web npx tsc -b            # typecheck
docker compose exec web npm run lint
```

## Architecture rules (do not break these)

1. **The client never calls Fake Store** (or any product source) directly. All
   product data goes through `/api/v1`, and `web/src/api/client.ts` is the
   only module that knows the API's location.
2. **All product access goes through `App\Catalogue\Contracts\ProductRepository`.**
   Never use `Http::` or upstream field names (`image`, `rating.rate`) outside a
   repository class. New sources = new repository + a case in
   `CatalogueServiceProvider`.
3. **"Top rated" is defined only in `App\Catalogue\TopRatedSelector`.** Don't
   re-implement ranking in controllers or the client.
4. **API responses go through API Resources** (`app/Http/Resources`). Changing a
   response shape is an API contract change: update `web/src/api/types.ts` and
   the tests on both sides.
5. **Never trust client-supplied product details.** Enquiries carry only
   `product_id`; the server looks the product up.
6. **Styling uses tokens** from `web/src/styles/tokens.css`; no hard-coded colours.
7. **No secrets in the repo.** New config goes in `config/catalogue.php` with an
   env var, documented in `api/.env.example` and the README table.

## Conventions

- PHP: Laravel defaults, Pint formatting, typed properties, `final readonly`
  value objects, thin controllers, FormRequests for validation.
- TypeScript: strict mode, function components, named exports, no `any`.
- Errors: the API always returns JSON `{message, errors?}`. Upstream failures
  are `ProductSourceUnavailable` (503). The client shows `message` to the user.

## Definition of done

- Behaviour change has a test (backend: `tests/Feature` with `Http::fake` and
  `Mail::fake`; frontend: `src/test` driving the real router and API client).
- Tests must not hit the network (`Http::preventStrayRequests()` is on).
- Pint, tsc, ESLint and both test suites pass.
- Docs updated if setup, config, API or assumptions changed.
