# T5 — Home spacing check + final verification

Status: verification note only. Under the scratch-only constraint no source
file was modified. `resources/js/pages/home.tsx` is **not** changed: the spacing
audit below finds the existing rhythm already correct once the T4 carousel
bleed is applied, so the preferred "no change" path is taken.

Source: `.scratch/budget-carousel-spec.md` §3, §7, §8 and
`.scratch/budget-carousel-tickets.md` T5.

Context: T1–T4 currently exist as drafts in `.scratch/`
(`T1-scroll-area.tsx`, `T2-scroll-area.tsx`, `T3-budget-item.tsx`,
`T4-budget-list.tsx`); the checked-in source is still the pre-carousel version.
The spacing maths below is for the integrated state (T4 draft in
`budget-list.tsx`), which is the state T5 verifies. No source changed, so
`git diff` against `HEAD` for `resources/`, `app/`, `routes/`, `database/`, and
`tests/` is empty.

## Spacing audit (no change proposed)

Reference geometry (Tailwind defaults, `border-box` from Preflight):

| Token      | Value | Used by                                      |
| ---------- | ----- | -------------------------------------------- |
| `p-4`      | 16px  | `AppLayout` padding                          |
| `gap-3`    | 12px  | `AppLayout` flex gap between page sections   |
| `mt-2`     | 8px   | Budgets heading (`HeadTitle className="mt-2"`) |
| `pb-2`     | 8px   | T4 carousel row bottom padding               |
| `max-w-md` | 448px | `AppLayout` column, padding included         |
| `w-64`     | 256px | Budget card                                  |

### Vertical rhythm

Flex gaps do not collapse with margins, so the distances add:

| Gap                                            | Calculation       | Result |
| ---------------------------------------------- | ----------------- | ------ |
| Section above → Budgets heading box            | `gap-3` + `mt-2`  | 20px   |
| Budgets heading box → first card top           | `gap-3`           | 12px   |
| Last card bottom → Goals heading box           | `pb-2` + `gap-3`  | 20px   |
| Goals heading box → Goals list (`GoalList`)    | `gap-3`           | 12px   |

Both headings therefore share the same 20px-above / 12px-below rhythm. The
`pb-2` on the carousel row is what restores the 20px above Goals; without it the
Goals heading would sit 12px under the cards and look glued to the row. It also
leaves room for the transient horizontal scrollbar. Keep both as-is.

`-mx-4` is horizontal only; it has no effect on vertical maths. No `mt-*` /
`mb-*` tightening is needed on the Goals heading and none is proposed.

### Budgets heading `mt-2`

`home.tsx` keeps `<HeadTitle className="mt-2" ...>` for the Budgets heading.
That 8px margin is intentional: it separates Budgets from the transaction
button / alert banner above while the layout `gap-3` supplies the base 12px.
Removing it would make Budgets 12px from the previous block while Goals stays
20px, breaking the symmetry above. Leave it.

### Carousel bleed `-mx-4` vs `AppLayout p-4`

- `AppLayout` renders `max-w-md p-4`; the column's total width is 448px and its
  content box is 416px (448 − 2 × 16).
- T4's `ScrollArea className="-mx-4"` cancels exactly that 16px on each side, so
  the scrolling viewport spans the column's full 448px box. The inner row's
  `px-4` re-adds 16px, so the first card's left edge aligns pixel-for-pixel with
  the Budgets heading, `BalanceComponent`, and every other child.
- No page overflow: the bleed grows the row only into the layout's own padding,
  never past `max-w-md`. On a viewport narrower than 448px the column is the
  viewport width and the bleed is exactly the viewport width. The Base UI
  `Viewport` additionally clips overflow, so cards do not widen the document.
- The pairing is a coupling: `-mx-4` / `px-4` must change together with
  `AppLayout`'s `p-4`. `AppLayout` currently has no responsive padding
  (`sm:p-6` etc.), so the values match at every breakpoint. Note this in review
  if layout padding is ever made responsive.

### Conclusion

No spacing change to `home.tsx`. `.scratch/T5-home-spacing.diff.txt` records the
no-change proposal (zero diff).

## Untouched confirmation

`git status --porcelain` is clean and
`git diff --stat HEAD -- resources app routes database tests` prints nothing.
Specifically:

- `BudgetEmpty` (`components/organisms/home/budget-empty.tsx`): unchanged; the
  `budgets.length === 0` branch in `home.tsx` still renders it full-width,
  outside any carousel.
- Goals (`home.tsx` Goals heading + `GoalList` / `GoalEmpty`): unchanged;
  remains a vertical list, no carousel, no bleed.
- Balance (`BalanceComponent`): import, props, and render position unchanged.
- Drawers (`TransactionDrawer`, `MoveMoneyDrawer`, `GoalDrawer`): unchanged;
  same props, state, and handlers. The T3 footer's `onMoveMoney(b.id)` reaches
  the existing `openMoveDrawer(budgetId)` with no new logic.
- `BottomNav`: unchanged, same `householdId` / `active="home"`.
- `home.tsx`: unchanged; no import, prop, state, or markup edit proposed.

## Scope confirmation

- No backend change: no `app/`, `config/`, `database/`, or `routes/` edits.
- No types change: `BudgetSummary` and the other `@/types` shapes are untouched.
- No routes change: no new/changed route in `routes/web.php` or `settings.php`.
- No new tests: layout-only change per spec §8 and repo conventions.
- Total source footprint of the whole feature remains the four scoped files:
  `components/ui/scroll-area.tsx` (new), `components/organisms/home/budget-list.tsx`,
  `components/organisms/home/budget-item.tsx`, and `pages/home.tsx` (no edit).

## Spec §7 acceptance checklist (manual eyeball)

Run against a household with an owner/editor account and a mix of normal and
over-budget budgets (include at least one long name and one over-budget card).
Use a dev server (`pnpm dev` or `composer dev`) at `max-w-md` width (≤ 448px) and
also a narrow phone width (~390px).

1. [ ] **Horizontal row, widths, peek, scroll**
       - ≥2 budgets render in a single row (no wrap), one card per budget.
       - Each card measures 256px (`w-64`) and does not stretch or shrink.
       - At 448px column width: content 416px − card 256px − gap 12px leaves
         ≈148px of the next card visible (~58%, about 1.5 cards).
         At 390px viewport: content 358px → ≈90px peek (~35%), still visible.
       - Trackpad/wheel/drag scrolls the row; the remaining cards are reachable
         and the page itself does not scroll horizontally.
2. [ ] **Card content**
       - Name is single-line with an ellipsis (`truncate`) and the chevron is
         visible; use a deliberately long name.
       - Left/over amount stays on one line: `Rp… left` normally,
         `Over by Rp…` in `text-destructive` when over.
       - `BudgetBar` reflects `pct` / `over`; Moved/Returned lines appear only
         when their amount is non-zero, styled as before.
3. [ ] **Footer action and tab order**
       - An over-budget card with `canEdit` shows the footer
         `Cover it from another budget` + `Move money`, separated by the top
         border and pinned to the bottom.
       - Clicking `Move money` opens `MoveMoneyDrawer` with the destination
         budget prefilled (the clicked card).
       - Inspect the DOM: the footer `button` is a sibling of the card `Link`,
         never a descendant of `a` (no button-in-anchor).
       - Keyboard: normal card = 1 tab stop (the link); over-budget card = 2
         (link, then `Move money`). Horizontal viewport may add its own focus
         stop only when scrollable; native focus rings remain visible.
4. [ ] **No footer on normal cards, equal heights**
       - Normal cards render no footer and no border.
       - In a mixed row, every card's top and bottom align (`items-stretch` +
         `h-full`); an over-budget footer does not make its card taller than
         the rest. Check with different content lengths as well.
       - `over && !canEdit` (viewer role): destructive amount still shows, no
         footer and no button.
5. [ ] **Scrollbar visibility**
       - Desktop at rest: the horizontal bar is invisible (`opacity-0`).
       - Hover the row: bar fades in (`data-hovering`); start scrolling: bar
         stays visible (`data-scrolling`); move away/stop: hidden again.
       - Bar is thin and horizontal, does not permanently cover card content.
       - Touch/device emulation: swipe scrolls, no persistent bar, and no
         console error/warning from Base UI.
6. [ ] **Unchanged surfaces**
       - 0 budgets: `BudgetEmpty` still full-width with its existing action.
       - Goals below stays a vertical list with the same heading/actions.
       - Balance summary, `Transaction` button, transaction/move/goal drawers,
         and `BottomNav` behave and look as before; no route or prop change.
7. [ ] **Static checks**
       - `pnpm types:check` passes.
       - `pnpm check` passes (lint + format + class sorting).
       - `pnpm build` succeeds (or the dev server renders the page above).

## Commands run

```bash
pnpm types:check   # tsc --noEmit -> exit 0, no diagnostics
pnpm check         # vp check -> 140 files formatted, 83 lint files, 0 problems
pnpm build         # vp build (Vite) -> built in ~7.8s
```

All three pass on the current source baseline.

`.scratch` coverage note: `tsconfig.json` includes only
`resources/js/**/*.ts`, `resources/js/**/*.d.ts`, and
`resources/js/**/*.tsx`, so `pnpm types:check` never sees scratch drafts.
`pnpm check` and `pnpm build` likewise validate the app source / module graph,
not standalone `.scratch` files (and `.scratch` is gitignored). The draft files
were type/formatted-checked separately in the T2–T4 notes. After the drafts are
copied into source, rerun all three commands against the integrated version.

## T5 acceptance

- [x] Budgets heading `mt-2`, carousel bleed, and Goals gaps verified even;
      no spacing edit needed.
- [x] `BudgetEmpty`, Goals, balance, drawers, and `BottomNav` untouched.
- [x] All 7 spec §7 criteria covered above with manual eyeball steps.
- [x] No backend, types, or routes changed; no new tests.
- [x] `pnpm types:check`, `pnpm check`, `pnpm build` pass.

## Constraints honoured

- Only `.scratch/` files created; `resources/js/` and all other source are
  read-only.
- `.scratch/` is gitignored (`.gitignore` line `/.scratch`), so this commit
  force-adds the T5 scratch files explicitly.
- No push.
