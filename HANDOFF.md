# Handoff: OHouse cleanup and review follow-up

## Where we are

- Repo: `ohouse`, branch `master`, remote `git@github.com:anggit-utomo/ohouse.app.git`.
- Fixed point for this work: `origin/master` = `b1f79a5`.
- **Six commits on top of that, all pushed, plus the current HEAD:**
    - `2bcd626` `test: decouple feature tests from the Vite manifest`
    - `82e4c67` `fix: clear PHPStan level 7 findings`
    - `3db71b1` `refactor: finish Base UI migration and drop dead settings code`
    - `63f288a` `fix: preserve transaction details in the budget edit drawer`
    - `902eda2` `docs: add handoff for review follow-up`
    - `a4cde7c` `fix: address transaction review findings`
    - HEAD: `refactor: finish transaction row cleanup`
- Working tree clean. `composer ci:check` is green: Pint, PHPStan 0 findings, `pnpm check`, `pnpm types:check`, Pest **181 passed / 1,167 assertions**.
- `CONTEXT.md` defines **Household**, **Member**, **Membership**, **Budget**, **Period**, **Transaction**, **Transfer**, and **Move**.

## What was done

- Tests no longer depend on a built Vite manifest (`withoutVite()` in `tests/TestCase.php`).
- PHPStan level 7 is clean. The `household_user` pivot is the typed `App\Models\Membership`; `stubs/PushSubscription.stub` covers the package model; `composer.json` `types:check` runs with `--memory-limit=1G`.
- The Base UI migration is finished: all `asChild` call sites use the native `render` prop. Dead settings files and the unrouted `Settings\ProfileController` were removed.
- `a4cde7c` fixed the three review findings: the budget-detail drawer derives its sign from `signed`, the history page locks closed periods, and the `PushSubscription` stub uses `ContentEncoding`.
- The current HEAD clears the follow-up judgement calls:
    - `EntryRecorded` uses `'transfer'`; "move" is the user action, not the record.
    - `Household::budgetBalances(?Period)` is the single budget-picker projection used by both controllers; the `default_amount ?? 0` drift is gone.
    - `App\Support\TransactionRow` owns both row shapes (`forBudget`, `forHistory`). Rows no longer carry `kind`; the pages derive transfer-ness from `type`.
    - `setIfNotNull` replaces the four Base UI `Select` null guards.
    - `HouseholdSettingsController` serialises dates with `toIso8601String()`.
    - `CONTEXT.md` gains Budget, Period, Transaction, Transfer, and Move.

## Decisions locked

- Base UI `render`; no `asChild` compatibility shim.
- Pivot named `Membership`.
- **Transfer** is the record; **Move** is the action that creates it.
- `budgetBalances(?Period)` is the only budget-picker projection; no presenter directory.
- `TransactionRow` is the only transaction-row presenter; rows carry `type`, never `kind`.
- `setIfNotNull` is the Base UI `Select` null guard; `budgets/edit` keeps its localised `periodError` cast.
- Dates sent to Inertia use `toIso8601String()`.
- User-menu Settings link uses `auth.user.current_household_id`, hidden when null.
- Keep the `profile.edit` / `appearance.edit` redirect routes and `OpenHouseholdSettings`.
- PHPStan memory limit lives in the `composer.json` script.
- `composer ci:check` is the finish line.
- No ADR; the durable vocabulary lives in `CONTEXT.md`.

## Open work

None from the review. The next useful pass is a fresh `/code-review` over `b1f79a5..HEAD`.

## Verification

```bash
composer ci:check
pnpm build
```

Expected: Pint passes, PHPStan 0, `pnpm check` and `pnpm types:check` pass, Pest 181/1,167.

## Running this on the work PC

1. The branch is pushed. Pull on the work PC, or clone the remote.
2. Run `composer setup` (installs PHP/JS deps, creates `.env`, generates the key, migrates, builds assets).
3. Run `composer dev` to serve.
4. The local SQLite database uses the demo users in `database/seeders/DemoSeeder.php`. Do not copy real credentials into the repo.

## Suggested skills

- `code-review` — run again over `b1f79a5..HEAD`.
- `laravel-best-practices` — backend changes to the presenter and model projection.
- `inertia-react-development` — page prop changes.
