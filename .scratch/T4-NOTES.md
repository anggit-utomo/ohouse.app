# T4 — BudgetList becomes the Budget Carousel (scratch draft)

Status: draft only. The real rewrite lands later in
`resources/js/components/organisms/home/budget-list.tsx`; under the scratch-only
constraint no source file was modified.

Source: `.scratch/budget-carousel-spec.md` §3 + §5 and
`.scratch/budget-carousel-tickets.md` T4.
Depends on `.scratch/T2-scroll-area.tsx` (orientation + scrollBarClassName) and
`.scratch/T3-budget-item.tsx` (the `w-64 h-full shrink-0` card).

## Draft

`.scratch/T4-budget-list.tsx` keeps the same props (`base`, `budgets`,
`canEdit`, `onMoveMoney`) and only changes the wrapper: a horizontal
`ScrollArea` with an inner `flex` row. No derived state, no handlers, no logic.
`home.tsx` can keep rendering `<BudgetList ... />` unchanged.

```tsx
<ScrollArea
    orientation="horizontal"
    scrollBarClassName="opacity-0 data-hovering:opacity-100 data-scrolling:opacity-100"
    className="-mx-4"
>
    <div className="flex items-stretch gap-3 px-4 pb-2">
        {budgets.map((b) => (
            <BudgetItem
                key={b.id}
                b={b}
                base={base}
                canEdit={canEdit}
                onMoveMoney={onMoveMoney}
            />
        ))}
    </div>
</ScrollArea>
```

The ticket lists the row classes as `flex gap-3 px-4 pb-2 items-stretch`; the
same class set is written sorted above (`flex items-stretch gap-3 px-4 pb-2`)
to match the project's Prettier/Tailwind class-sorting convention. Order is
irrelevant to the result.

Key change: `key={`${b.id}-${idx}`}` becomes `key={b.id}`. Ids are unique and
the list is stable, so the index suffix was only masking nothing. No
`snap-x`, no `scroll-snap-*`, no extra wrapper, no `space-x-*`.

## Peek notes

- `max-w-md` = 28rem = 448px; `AppLayout` uses `p-4` (16px each side), so the
  content column is 416px on wide phones.
- One card is `w-64` = 256px; `gap-3` = 12px. The next card therefore peeks
  `416 - 256 - 12 = 148px` (~58% of a card), i.e. roughly one and a half cards
  visible. That peek is the scroll affordance; no arrows, dots, or badges.
- On a narrower viewport the column shrinks but the card stays 256px, so the
  peek never disappears entirely (it is still ~35% at 390px). `w-64` is
  deliberately not `w-full`, otherwise there would be no cue that more cards
  exist.
- Single budget still renders one 256px card at the start of the row, so it
  appears left-aligned with the rest of the column and does not stretch.
- Zero budgets never reach this component: `home.tsx` renders `BudgetEmpty`
  instead (spec §6), so the carousel has no empty state of its own.

## Bleed notes

- `AppLayout` already pads the page with `p-4`. `-mx-4` on the `ScrollArea`
  cancels that padding so cards can be clipped at the screen edge while
  scrolling; the row's own `px-4` restores the start inset so the first card
  still aligns with the heading and the rest of the column.
- Net width: column + 32px = page width while the page is ≤ `max-w-md`; on
  wider screens it is 480px inside the 448px column. That stays inside the
  viewport, and the `Viewport` clips its own content, so no horizontal page
  scrollbar is introduced.
- `-mx-4` is coupled to the parent's `p-4`. If `AppLayout` spacing changes,
  both values must change together; otherwise the first card no longer aligns
  or the bleed leaks past the edge.
- The horizontal scrollbar is absolutely positioned on the `ScrollArea` root,
  so it spans the full bleed width rather than just the column. It is invisible
  until hover/scroll, so this is only a transient detail; the visible bar is
  still thin (`h-2.5`) and centered on the same row.

## Equal-height notes

- The row uses `items-stretch`; every card already declares `h-full` (T3), so
  all cards take the tallest card's height regardless of whether they show a
  footer.
- The `Card` is a flex column; the `Link` has `flex-1` and the footer uses
  `mt-auto`. On a short normal card the link absorbs the extra space, keeping
  header/content top-aligned; on an over-budget card the footer is pinned to
  the bottom edge.
- Footer is only rendered for `over && canEdit`; normal cards never pay for
  the footer's height, and the stretch is what keeps the row visually even.
- No fixed row height is set. Height comes from the tallest content (long
  moved/returned lines or an over-budget footer), so text scaling and larger
  amounts do not clip.

## Overflow / scrollbar notes

- `ScrollArea` renders the children inside Base UI's `Viewport`
  (`overflow: scroll` with native bars suppressed) and adds a custom
  `ScrollBar orientation="horizontal"`. Touch swipe, trackpad/wheel, and
  keyboard scrolling are supplied by the viewport; there is no JS in this
  component.
- Hidden-until-interaction: `scrollBarClassName="opacity-0
  data-hovering:opacity-100 data-scrolling:opacity-100"`. Base UI sets
  `data-hovering` / `data-scrolling` on the Scrollbar, so desktop users see the
  bar only while interacting. Touch has no persistent indicator; the peek is
  the accepted affordance (spec §3).
- `pb-2` (8px) leaves room under the cards so the ~10px bar does not cover the
  footer/amount during its brief appearance. The bar is overlay-positioned, so
  it can still overlap the last 2px of padding; if visual QA shows it touching
  card content, bump to `pb-2.5` rather than adding margin to the cards.
- No `snap-x` / `snap-mandatory`: scrolling is free, matching spec §3 and
  leaving native momentum behaviour intact.
- Base UI keeps the viewport out of the tab order when it cannot scroll
  (`tabIndex=-1`), so a single-budget row adds no extra stop. When it can
  scroll, the viewport is focusable so keyboard users can arrow through the
  row; per-card tab stops from T3 are unchanged (link, plus `Move money` when
  over-budget and editable).
- `items-stretch` plus `h-full` must not be paired with a fixed height on the
  row; the viewport clips horizontally only when the row is actually taller
  than the root, which the equal-height rules prevent.

## Acceptance (ticket T4)

- [ ] `BudgetList` returns a horizontal `ScrollArea` with `-mx-4`.
- [ ] Inner row is `flex items-stretch gap-3 px-4 pb-2`.
- [ ] Cards map with `key={b.id}` (index suffix dropped).
- [ ] `base`, `canEdit`, `onMoveMoney`, and `b` pass through unchanged.
- [ ] No snap, no extra wrapper, no logic change.
- [ ] >=2 budgets scroll with ~1.5-card peek; 1 budget left-aligns; row
      heights equal.
- [ ] Bar hidden until hover/scroll on desktop; touch swipe works with no
      persistent bar.
- [ ] No horizontal page overflow from `-mx-4`.
- [ ] `pnpm types:check` / `pnpm check` pass once copied into place (layout
      only; no new tests, spec §8).

## Verification performed in this scratch run

- Draft type-checked with a temporary config extending `tsconfig.json` and
  including only the T4 draft (plus its existing imports), removed afterwards:

    ```bash
    pnpm exec tsc --noEmit -p .scratch/.tsconfig-t4.json
    ```

- Formatting and class sorting:

    ```bash
    pnpm exec vp check .scratch/T4-budget-list.tsx
    ```

    The repo `tsconfig.json` only includes `resources/js/**`, so the draft is not
    part of `pnpm types:check` until copied into place.

## Constraints honoured

- Only `.scratch/` files were created/modified; `resources/js/` is read-only.
- No backend, types, routes, or tests changed (layout-only).
- `.scratch/` is gitignored (`.gitignore` line `/.scratch`), so this commit
  force-adds the two T4 scratch files explicitly.
