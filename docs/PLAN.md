# Plan / spec (as approved before building)

This is the plan Claude produced from my brief and design proposal, after reading
the challenge PDF. I approved it with all seven suggested changes before any code
was written. Differences between plan and result are noted at the end.

## Changes to my original design (all accepted)

1. **One definition of "top rated".** My design used curated IDs on the home
   page but API rating on category pages. Now there's a single `TopRatedSelector`:
   curated first, topped up by rating to N=3 on category pages.
2. **Search in the repository** (`ProductRepository::search()`), not the
   controller, so a future source with native search can push it down.
3. **Don't leak Fake Store's shape**: map to a domain `Product`, expose via API Resources.
4. **Fixture data source** (`PRODUCT_SOURCE=fakestore|fixture`) as a demo
   safety net and proof that the seam is real.
5. **Enquiries send only `product_id`**; the server resolves the product.
6. **No database**: file cache and synchronous mail. Enquiry persistence is the first next step.
7. **`/api/v1` prefix** and a **GitHub Actions CI** workflow.

## Spec

- **Backend:** `Product`/`Category` value objects; `ProductRepository`
  (`all, find, categories, inCategory, search`); Fake Store and fixture
  implementations; `CatalogueServiceProvider` binds by config; thin controllers
  and API Resources; `StoreEnquiryRequest`; `ProductEnquiryMail` with Reply-To;
  `throttle:enquiries` (5/min/IP); 503 when the upstream is down.
- **Endpoints:** `GET products/featured`, `GET categories`,
  `GET categories/{slug}/products` → `{top_rated, others}`, `GET products?search=`,
  `GET products/{id}`, `POST enquiries` → 202.
- **Frontend:** typed API client behind the Vite `/api` proxy; routes `/`,
  `/category/:slug`, `/search?q=`, `/products/:id`, `/products/:id/enquire`;
  design tokens; loading, error and empty states everywhere; only `react-router` added.
- **Docker:** `api` (PHP CLI + Composer; entrypoint creates `.env` and app key),
  `web` (Node 22, Vite), `mailpit`. One command.
- **Tests:** Laravel feature tests (`Http::fake`, `Mail::fake`; featured order,
  category top-up, search, 404, enquiry 202/422/429); Vitest + Testing Library
  (search, enquiry thank-you and errors, home).
- **Process:** small commits on `feat/product-catalogue` → PR to `main`; running
  log of prompts and AI mistakes in `docs/AI_LOG.md`.

## Plan vs result

- PHP **8.4**, not 8.3: the lock file was resolved on PHP 8.5 and needs ≥ 8.4.1 (see AI_LOG mistake #1).
- Added beyond the plan: an upstream-outage-not-cached test, a malformed-data
  test, a mail-transport-failure test, an HTML-escaping test, and Pint in CI.
