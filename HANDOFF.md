# Handoff: OHouse cleanup and review follow-up

## Where we are

- Repo: `ohouse`, branch `master`, remote `git@github.com:anggit-utomo/ohouse.app.git`.
- Fixed point for this work: `b1f79a5`. Current `origin/master` is `a4cde7c`.
- **Substantive commits on top of the fixed point; HEAD is ahead of `origin/master` and not pushed:**
    - `2bcd626` `test: decouple feature tests from the Vite manifest`
    - `82e4c67` `fix: clear PHPStan level 7 findings`
    - `3db71b1` `refactor: finish Base UI migration and drop dead settings code`
    - `63f288a` `fix: preserve transaction details in the budget edit drawer`
    - `902eda2` `docs: add handoff for review follow-up`
    - `a4cde7c` `fix: address transaction review findings`
    - `58d574f` `refactor: finish transaction row cleanup`
    - `281219a` `docs: resolve transaction and transfer vocabulary`
    - `fdd97f2` `chore: ignore the local scratch tracker`
    - `a3701d9` `refactor: align transfer vocabulary and history labels`
    - `ba51811` `test: cover the transfer notification path`
    - `2716665` `refactor: require a period for budget balances`
- Working tree clean. `composer ci:check` is green: Pint, PHPStan 0 findings, `pnpm check`, `pnpm types:check`, Pest **185 passed / 1,179 assertions**.
- `CONTEXT.md` defines **Household**, **Member**, **Membership**, **Budget**, **Period**, **Transaction**, **Transfer**, and **Move**.

## What was done

- Tests no longer depend on a built Vite manifest (`withoutVite()` in `tests/TestCase.php`).
- PHPStan level 7 is clean. The `household_user` pivot is the typed `App\Models\Membership`; `stubs/PushSubscription.stub` covers the package model; `composer.json` `types:check` runs with `--memory-limit=1G`.
- The Base UI migration is finished: all `asChild` call sites use the native `render` prop. Dead settings files and the unrouted `Settings\ProfileController` were removed.
- `a4cde7c` fixed the three review findings: the budget-detail drawer derives its sign from `signed`, the history page locks closed periods, and the `PushSubscription` stub uses `ContentEncoding`.
- `58d574f` cleared the follow-up judgement calls:
    - `EntryRecorded` uses `'transfer'`; "move" is the user action, not the record.
    - `App\Support\TransactionRow` owns both row shapes (`forBudget`, `forHistory`). Rows no longer carry `kind`; the pages derive transfer-ness from `type`.
    - `setIfNotNull` replaces the four Base UI `Select` null guards.
    - `HouseholdSettingsController` serialises dates with `toIso8601String()`.
    - `CONTEXT.md` gains Budget, Period, Transaction, Transfer, and Move.
- The fix pass (`fdd97f2`..HEAD) closed the remaining review findings:
    - History labels a Transfer as "Transfer"; the "Moved A → B" title and the "Move money" action keep the verb. The move title is built only for transfers.
    - `Household::budgetBalances(Period)` requires a Period; the unreachable current-period default is gone.
    - `CONTEXT.md` defines Transaction as the row (including transfer legs), Transfer as the pair, and Move as the action.
    - Three regression tests cover the history row contract, the transfer notification copy, and the dispatched event kind.
    - `.scratch` is gitignored; the local spec and tickets live there.
    - `AGENTS.md` uses "two linked transactions" and lists the row presenter. The file is gitignored, so this edit is local only.

## Decisions locked

- Base UI `render`; no `asChild` compatibility shim.
- Pivot named `Membership`.
- **Transfer** is the record; **Move** is the action that creates it.
- `budgetBalances(Period)` is the only budget-picker projection; no presenter directory.
- `TransactionRow` is the only transaction-row presenter; rows carry `type`, never `kind`.
- History labels a Transfer as "Transfer"; the "Moved A → B" title and the "Move money" action keep the Move verb.
- The transfer notification path is locked by regression tests at the listener and event seams.
- The local spec and tickets live under `.scratch`, which is gitignored.
- `setIfNotNull` is the Base UI `Select` null guard; `budgets/edit` keeps its localised `periodError` cast.
- Dates sent to Inertia use `toIso8601String()`.
- User-menu Settings link uses `auth.user.current_household_id`, hidden when null.
- Keep the `profile.edit` / `appearance.edit` redirect routes and `OpenHouseholdSettings`.
- PHPStan memory limit lives in the `composer.json` script.
- `composer ci:check` is the finish line.
- No ADR; the durable vocabulary lives in `CONTEXT.md`.

## Open work

None from the review. The fix pass is complete; the closing `/code-review` runs over `281219a..HEAD`.

## Verification

```bash
composer ci:check
pnpm build
```

Expected: Pint passes, PHPStan 0, `pnpm check` and `pnpm types:check` pass, Pest 185/1,179.

## Running this on the work PC

1. The branch is local: `origin/master` is behind. Push before switching machines, then pull on the work PC or clone the remote.
2. Run `composer setup` (installs PHP/JS deps, creates `.env`, generates the key, migrates, builds assets).
3. Run `composer dev` to serve.
4. The local SQLite database uses the demo users in `database/seeders/DemoSeeder.php`. Do not copy real credentials into the repo.

## Suggested skills

- `code-review` — run again over `281219a..HEAD`.
- `laravel-best-practices` — backend changes to the presenter and model projection.
- `inertia-react-development` — page prop changes.
