# AI usage

The full running log (timeline, every prompt that mattered, every mistake) is in
[docs/AI_LOG.md](docs/AI_LOG.md). This is the summary.

## Tools and models

- **Claude Code** (CLI) with **Claude Opus 5.5** — planning, building, testing,
  docs. Everything ran in Docker; PHP and Composer are not installed on my machine.
- **A second Claude sub-agent** (fresh context, read-only) as an independent
  code reviewer before opening the PR.
- No other AI tools.

## Agent instruction files

- [`CLAUDE.md`](CLAUDE.md) — committed. Project commands, the seven
  architecture rules agents must not break (e.g. "the client never calls Fake
  Store", "all product access goes through `ProductRepository`", "'top rated'
  is defined only in `TopRatedSelector`"), conventions, and a definition of done.
  Written after the architecture settled so it describes the code as it is,
  ready to feed the next agent run (or the live change in the interview).

## Plan / spec

[`docs/PLAN.md`](docs/PLAN.md). Workflow: I gave Claude the brief, my own
design and a rule to **show the plan and wait for my go-ahead**, and to
**challenge my design where it disagreed**. It read the PDF, checked the
environment, and came back with seven changes to my design. I accepted all
seven; the most important:

- My design used "top rated" in two different senses (curated on the home
  page, API rating on category pages). Now one definition, one class.
- Add a second data source (bundled fixture) so the "data source will change"
  claim is proven by code, and the demo survives Fake Store outages.

## Key prompts and moments

1. **The opening brief** — full challenge summary, proposed design, and working
   rules: *"First, show me the plan/spec and wait for my go-ahead"*, *"challenge
   it if you disagree"*, *"keep a running log of my key prompts and any AI
   mistakes"*, *"explain the important decisions — I have to defend every line"*.
   This set up plan-first, review-before-build, and the log this file is built on.
2. **Identity check before any commit.** Claude noticed my global git email is
   my work address and that `gh`/SSH were on my work account. The repo has a
   local identity and pushes over HTTPS as my personal account.
3. **"Prove the tests can fail."** After both suites passed first time, Claude
   deliberately broke the curated-first rule and the API error handling. The
   tests caught both (3 failures each). A green suite that has never been red
   proves little.
4. **Independent review.** A fresh sub-agent reviewed the branch against the
   acceptance criteria and the five assessment areas. It returned 14 findings;
   each was verified before fixing (e.g. running both email validators side by
   side in the container), 12 were fixed with a regression test, 1 was a false
   positive (uncommitted docs) and 1 was declined with a reason (see below).
5. **Declining a suggestion.** The reviewer suggested silently falling back to
   fixture data when Fake Store is down. Declined: it would hide a real outage
   from the owner. The switch stays explicit (`PRODUCT_SOURCE=fixture`).

## Where the AI got things wrong, and how it was caught

The most instructive ones (all 13 + 12 reviewer findings are in the log):

| What went wrong | Caught by | Fix |
|---|---|---|
| Scaffolded Laravel with a PHP 8.5 Composer image but wrote a PHP 8.3 runtime image; the lock file needed ≥ 8.4.1. | `docker compose up` failing | PHP 8.4 runtime |
| **All customers shared one enquiry rate-limit bucket**, because behind the dev proxy every request came from the same container IP. | Independent reviewer | Trusted proxies only (never `*`) + tests for real-IP and spoofing |
| **Search "men" returned all women's clothing** (substring match). | Independent reviewer | Word-prefix matching + test |
| An email address that passes Laravel's `email` rule crashed the mailer with a 500. | Independent reviewer, verified in container | `email:rfc,strict` + test |
| `APP_DEBUG=true` default leaked stack traces in API errors. | My curl smoke test | Default `false` |
| Test dependencies were "installed" with `--silent` and never actually installed. | `vitest: not found` | Reinstall with output visible |
| Trusted-proxy config read `env()` in `bootstrap/app.php`; it would silently stop working under `config:cache` in production. | Self-review right after writing | Moved to config + service provider |
| While mutation-testing a fix, restored the file with `git checkout --`, which **also wiped the uncommitted fix**. | A guard check in the same command printed a warning | Re-applied, re-ran everything |

What I take from this: the AI was fast and mostly right, but its errors were
the subtle kind that pass a quick look. That includes code that looks correct
but is wrong in deployment (proxies, `config:cache`), and tests that pass
without testing what their name says. What caught them was a layered review:
running the real stack, mutation checks, an independent reviewer with a
different context, and re-reading tests against their names.

## Time spent

Measured from the session timestamps (IST, 23 Nov 2026, 09:40 → ~10:45):

| Phase | Time | What |
|---|---|---|
| Planning | ~15 min (09:40–09:55) | Brief → plan → my review and approval |
| Building | ~30 min (09:55–10:26) | Scaffold, API, client, Docker, smoke tests |
| Testing | ~5 min (10:26–10:31) | Both test suites, mutation checks, CI workflow |
| Reviewing | ~15 min (10:31–10:45) | Independent review, 12 fixes with tests, docs, PR |
| **Total (agent session)** | **~1 h 05 min** | |

This is the wall-clock time of the AI session. It does not include the time I
spent afterwards reading through every file myself to prepare for the
follow-up interview.
