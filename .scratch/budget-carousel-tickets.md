# Tickets — Home Budgets Horizontal Card Carousel

> Note: Real implementation commits (scratch drafts superseded).

Source: `.scratch/budget-carousel-spec.md` + ADR-0003. Frontend-only, no backend change.
Conventions: pnpm (never npm), `cn()`, semantic tokens, `flex gap-*`, `truncate`, strict TS, single quotes, 4-space indent.

---

## T1 — Add shadcn ScrollArea

Status: done
Commit: 9660a05da58f2c0dabd9dc8954a19e26736c4c81
Depends: none.
Files: `resources/js/components/ui/scroll-area.tsx` (new, generated).

Do:
- Run `pnpm dlx shadcn@latest add @shadcn/scroll-area`.
- Confirm Base UI import (`@base-ui/react/scroll-area`, already in deps) and that the file lands under `resources/js/components/ui/`.
- Do not hand-write the component; do not edit other `ui/` files.

Accept:
- `scroll-area.tsx` exists and exports `ScrollArea`, `ScrollBar`.
- `pnpm types:check` passes.

## T2 — Minimal horizontal extension on ScrollArea

Status: done
Commit: 664b50549b6bd0104ff6bf6d5ae6e42e4f2ccfa6
Depends: T1.
Files: `resources/js/components/ui/scroll-area.tsx` (edit, minimal).

Do:
- Add optional props: `orientation?: 'vertical' | 'horizontal'` (default `vertical`), `scrollBarClassName?: string`, `viewportClassName?: string`.
- Render `<ScrollBar orientation={orientation} className={scrollBarClassName} />` accordingly; vertical path identical to generated output.
- Add a short comment marking it as a deliberate extension (so `shadcn diff` merges stay obvious).
- No snap logic, no behaviour change for vertical users.

Accept:
- `<ScrollArea orientation="horizontal" />` shows a horizontal bar; default shows vertical.
- Hidden-until-hover override works via `scrollBarClassName="opacity-0 data-hovering:opacity-100 data-scrolling:opacity-100"`.
- `pnpm check` passes (lint/format, class sorting).

## T3 — BudgetItem becomes a Budget Card

Status: done
Commit: f7721baa2d252bf1d32db12596cbb6215dd6cb4a
Depends: none (parallel with T1/T2).
Files: `resources/js/components/organisms/home/budget-item.tsx` (rewrite).

Do:
- Rewrite as `Card size="sm"` with `className="w-64 shrink-0 h-full"`.
- `CardHeader`: `CardTitle` name + chevron (`truncate`), `CardAction` left/over text (`whitespace-nowrap`, destructive when `over`).
- Keep `leftText()` copy exactly: `Over by {rupiah(-left)}` / `{rupiah(left)} left`.
- `CardContent`: `BudgetBar`, `MovedToGoals`, `ReturnedFromGoals` unchanged.
- `CardFooter` only when `b.over && canEdit`: `Cover it from another budget` + `Move money` button calling `onMoveMoney(b.id)`, `mt-auto` + top border.
- `Link` wraps Header+Content only (`flex flex-1 flex-col`); footer button stays outside (no button-in-a).
- Subtle hover only, semantic tokens, `cn()` for conditionals.

Accept:
- Long names truncate, amounts stay on one line, bar/moved lines render as before.
- No `button` inside `a` in DOM; keyboard gives 1 stop (normal) / 2 stops (over-budget).
- `pnpm types:check` passes.

## T4 — BudgetList becomes the Budget Carousel

Status: done
Commit: 70f234b003847bdf26481fad65b203cd21731ceb
Depends: T2, T3.
Files: `resources/js/components/organisms/home/budget-list.tsx` (rewrite).

Do:
- Return `<ScrollArea orientation="horizontal" scrollBarClassName="opacity-0 data-hovering:opacity-100 data-scrolling:opacity-100" className="-mx-4">` with inner `<div className="flex gap-3 px-4 pb-2 items-stretch">`.
- Map `budgets` to `BudgetItem` with `key={b.id}` (drop index suffix).
- No snap classes, no extra wrappers, no logic change (props pass-through).

Accept:
- >=2 budgets scroll horizontally with ~1.5-card peek; 1 budget left-aligns; row heights equal.
- Bar hidden until hover/scroll on desktop; touch swipe works with no persistent bar.
- No horizontal page overflow from `-mx-4` (inner `px-4` compensates).

## T5 — Home spacing check + final verification

Status: done
Commit: 3db02bdd4c3c4560190321ad64db44f4ecb2af3b
Depends: T4.
Files: `resources/js/pages/home.tsx` (spacing only if needed), no logic change.

Do:
- Verify Budgets heading (`mt-2`), carousel, Goals heading gaps look even with the bleed; tighten only if visually off.
- Confirm `BudgetEmpty`, Goals, balance, `TransactionDrawer`, `MoveMoneyDrawer`, `GoalDrawer`, `BottomNav` untouched.
- Run `pnpm types:check`, `pnpm check`, and `pnpm build` (or dev-server eyeball: widths, peek, hover bar, footer action, tab order, mobile swipe).

Accept:
- All 7 spec acceptance criteria pass.
- No backend, types, or routes changed; no new tests (layout-only).

---

Suggested order: T1 → T2 → T3 (parallel OK) → T4 → T5.
