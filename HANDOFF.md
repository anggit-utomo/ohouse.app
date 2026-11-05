# Handoff: Tests and controllers

## Context

This work aligned the Laravel feature tests with the current household-scoped
routes and controller behavior in `/home/anggit/exp-app`.

## Decisions

- Household-scoped settings routes are the intended contract.
- Transaction details use the history drawer, not a separate detail page.
- Feature tests should cover the current routes, including push subscription and
  notification preference endpoints.

## Changes completed

- Updated stale settings, transaction, transfer, report, setup, and flash-message
  test expectations.
- Removed the transaction show route/action; push notifications now link to
  transaction history.
- Fixed settings redirect model binding, account deletion logout ordering, and
  the member-role success message.
- Eager-loaded transaction relations to satisfy the query-count regression test.
- Added feature tests for push subscriptions and notification settings.

## Verification

- `php artisan test --compact`: 178 passed, 1,133 assertions.
- Pint and `pnpm check`: passed.
- `composer test` is blocked by 31 PHPStan findings.
- `pnpm types:check` reports frontend type errors unrelated to this work.

## Suggested skills

- `laravel-best-practices`
- `testing-best-practices`
- `inertia-react-development` if continuing into frontend work
