# Handoff: OHouse cleanup and review follow-up

## Where we are

- Repo: `ohouse`, branch `master`, remote `git@github.com:anggit-utomo/ohouse.app.git`.
- Fixed point for this work: `origin/master` = `b1f79a5`.
- **Four commits ahead of `origin/master`, not pushed:**
  - `2bcd626` `test: decouple feature tests from the Vite manifest`
  - `82e4c67` `fix: clear PHPStan level 7 findings`
  - `3db71b1` `refactor: finish Base UI migration and drop dead settings code`
  - `63f288a` `fix: preserve transaction details in the budget edit drawer`
- Working tree clean. `composer ci:check` is green: Pint, PHPStan 0 findings, `pnpm check`, `pnpm types:check`, Pest **179 passed / 1,145 assertions**.
- `CONTEXT.md` now defines **Household**, **Member**, and **Membership**. Read it before touching membership or pivot code.
- The old `HANDOFF.md` was deleted in `3db71b1`; this file replaces it.

## What was done

Reference the commits above and `CONTEXT.md`; the diffs are the source of truth.

- Tests no longer depend on a built Vite manifest (`withoutVite()` in `tests/TestCase.php`).
- PHPStan level 7 is clean. The `household_user` pivot is now the typed `App\Models\Membership`; `stubs/PushSubscription.stub` covers the package model; `composer.json` `types:check` runs with `--memory-limit=1G`.
- The Base UI migration is finished: all `asChild` call sites use the native `render` prop. Dead settings files and the unrouted `Settings\ProfileController` were removed.
- Smoke-test fixes: the budget detail page now sends transaction note/payer/budget/type; the delete-household confirm uses the passkey destructive style; edit/delete is gated on `locked`.

## Decisions locked

- Base UI `render`; no `asChild` compatibility shim.
- Pivot named `Membership`.
- User-menu Settings link uses `auth.user.current_household_id`, hidden when null.
- Keep the `profile.edit` / `appearance.edit` redirect routes and `OpenHouseholdSettings`.
- PHPStan memory limit lives in the `composer.json` script.
- `composer ci:check` is the finish line.
- No ADR; the durable vocabulary lives in `CONTEXT.md`.

## Open work from `/code-review`

Three real findings, in priority order:

1. **Transfer-in sign regression on the budget detail drawer.**
   `app/Http/Controllers/BudgetController.php` sends `type` as `transfer_in` / `transfer_out`. `resources/js/components/organisms/transactions/transaction-detail-drawer.tsx` computes `plus = t.type === 'income'`, so a transfer-in shows `-` while the list above shows `+` (`TransactionTypeEnum::TRANSFER_IN->sign()` is `+1`). Fix by sending only `expense`/`income` for the drawer or deriving the sign from `signed`; add a regression test.

2. **Closed-period actions still appear on the history page.**
   `resources/js/pages/transactions/index.tsx` does not pass `locked`, and `TransactionController@index` sends no `closed` flag. A closed period selected from the month picker still exposes Edit/Delete, which the backend then rejects. Send `closed` from the controller and pass `locked={closed}`; add a test.

3. **Stub type is inaccurate.**
   `stubs/PushSubscription.stub` declares `content_encoding` as `string|null`, but the package casts it to `ContentEncoding|null`. Fix the stub or drop the property.

Judgement calls from the review — decide, don't blindly fix:

- The transaction row shape is duplicated between `BudgetController@show` and `TransactionController@index`; a presenter would remove the drift.
- The `Household::budgetBalances()` null fix exists in one of three copies; `BudgetController:69` and `TransactionController:59` still lack `?? 0`.
- Four duplicated Base UI `Select` null guards; `budgets/edit` `periodError` is a cast rather than a real form type.
- `kind` vs `type` on the same row is ambiguous; `CONTEXT.md` defines neither.
- `HouseholdSettingsController` uses `format(DATE_ATOM)` where other controllers use `toIso8601String()`.

False positives from the review — do **not** "fix" these:

- `first()->allocated ?? …` is safe; PHP's `??` suppresses null property access. Same for `$invite->code ?? $code`.
- `nav-user` still tracks the trigger width: `DropdownMenuContent` sets `w-(--anchor-width)` itself.
- `ReportController`'s `$before !== null` is needed to narrow `$before` for PHPStan.

## Verification

```bash
composer ci:check
pnpm build
```

Expected: Pint passes, PHPStan 0, `pnpm check` and `pnpm types:check` pass, Pest 179/1,145.

## Running this on the work PC

1. Push from the Mac first (`git push origin master`), then pull on the work PC, or clone the remote.
2. Run `composer setup` (installs PHP/JS deps, creates `.env`, generates the key, migrates, builds assets).
3. Run `composer dev` to serve.
4. The local SQLite database uses the demo users in `database/seeders/DemoSeeder.php`. Do not copy real credentials into the repo.
5. The diagnostic transaction created while tracing the note bug was removed.

## Suggested skills

- `diagnosing-bugs` — reproduce the transfer sign and closed-period issues before fixing.
- `tdd` — add regression tests for both.
- `laravel-best-practices` / `php-best-practices` — backend payload and controller changes.
- `inertia-react-development` — drawer/controller prop changes.
- `code-review` — run again on the follow-up commit.

## Shutdown note

The dev server (`composer dev`) is running in the background and will stop when the Mac shuts down. **Push the branch before shutting down**, or the four code commits and this handoff stay only on the Mac.
