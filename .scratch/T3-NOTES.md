# T3 — BudgetItem becomes a Budget Card (scratch draft)

Status: draft only. The real rewrite lands later in
`resources/js/components/organisms/home/budget-item.tsx`; under the scratch-only
constraint no source file was modified.

Source: `.scratch/budget-carousel-spec.md` §4 and
`.scratch/budget-carousel-tickets.md` T3.

## Draft

`.scratch/T3-budget-item.tsx` is a drop-in replacement for the current
`BudgetItem`. Props are unchanged (`b`, `base`, `canEdit`, `onMoveMoney`), so
`BudgetList` and `home.tsx` need no signature change.

## Structure

```
Card (size="sm", h-full w-64 shrink-0, subtle hover ring)
├── Link (flex flex-1 flex-col gap-(--card-spacing))
│   ├── CardHeader
│   │   ├── CardTitle (flex min-w-0 items-center gap-0.5)
│   │   │   ├── span.truncate  → b.name
│   │   │   └── ChevronRightIcon (size-4 shrink-0, muted)
│   │   └── CardAction (text-sm whitespace-nowrap; destructive when over)
│   └── CardContent
│       ├── BudgetBar pct over
│       ├── MovedToGoals amount moved
│       └── ReturnedFromGoals amount returned
└── CardFooter (only when b.over && canEdit; mt-auto justify-between border-t)
    ├── span → "Cover it from another budget"
    └── button → "Move money" → onMoveMoney?.(b.id)
```

- `leftText()` copy is kept byte-for-byte:
  `Over by {rupiah(-left)}` / `{rupiah(left)} left`.
- `BudgetBar`, `MovedToGoals`, and `ReturnedFromGoals` are unchanged.
- The Link is an intermediate wrapper, so the Card's `gap-(--card-spacing)`
  no longer spaces Header from Content directly; the same gap is repeated on
  the Link. The Card's own gap still spaces the Link from the footer.
- Semantic tokens only (`bg-card`, `text-muted-foreground`,
  `text-destructive`, `ring-foreground/20`). No hard-coded colours.
- Hover is a ring-colour change plus `transition`; no transform, no motion that
  needs a reduced-motion guard.
- `cn()` is used for the conditional amount colour; no template-string classes.

## Accessibility notes

- **No interactive element inside a link.** The `Move money` `<button>` is a
  sibling of the `<Link>`, never a descendant. Anchor content is limited to the
  header and the content block, so the DOM has no `button` inside `a` and no
  invalid nested interactive markup.
- **Tab stops.** A normal card contributes exactly one stop (the card link).
  An over-budget card the user can edit contributes two: the link, then
  `Move money`. Over-budget cards without edit permission stay at one stop
  because the footer is not rendered.
- **Split target is deliberate.** The footer strip is not part of the link.
  Clicking the footer whitespace does nothing; only the named button moves
  money. This prevents an accidental navigation when aiming for the action.
- **Visible text for the action.** The link's accessible name comes from the
  budget name (the amount text is inside the link too). The button name is the
  visible `Move money`; no icon-only control.
- **Colour is not the only signal.** `text-destructive` is paired with the
  literal words `Over by …`, so the over state is still announced by text.
- **Native focus rings kept** per spec §6. The surrounding `Card` uses
  `overflow-hidden`, which can clip a default outline at the card edges. If
  visual QA shows the link focus indicator is lost, add
  `focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset` to the
  Link rather than removing the outline. Do not disable focus styling.
- **Chevron is decorative.** It is an affordance next to the name; the link
  text already carries the name. If stricter AT hygiene is wanted, mark the
  icon `aria-hidden`, matching how other decorative chevrons in the app are
  rendered.

## Truncation notes

- `CardHeader` is a two-column grid (`1fr auto`) whenever `CardAction` is
  present.
- `CardTitle` gets `min-w-0` so the `1fr` track may shrink below the name's
  intrinsic width; without it a long name would push the amount off-card.
- The name span uses `truncate` (`overflow-hidden text-ellipsis
  whitespace-nowrap`); the chevron uses `shrink-0`.
- `CardAction` uses `whitespace-nowrap`, so the amount never wraps to a second
  line and always keeps its intrinsic width. A long name truncates before the
  amount is squeezed.
- Full names remain available on `/budgets/{id}` (the link target), so
  truncation is lossy only on the summary surface.

## Equal-height notes

- The row (T4) uses `items-stretch`; each card also declares `h-full`, so the
  shortest card stretches to the tallest sibling's height.
- `Card` is already a flex column. The Link has `flex-1`, so it absorbs the
  free vertical space, and the footer uses `mt-auto` to sit at the bottom edge.
- Normal cards have no footer; the Link simply fills the card, leaving the
  header/content pinned to the top while the card keeps the shared row height.
- Footer is skipped for `over && !canEdit`, so no empty bordered strip is
  rendered and the card does not pay footer height for a hidden action.

## Acceptance (ticket T3)

- [ ] `Card size="sm"` with `h-full w-64 shrink-0`.
- [ ] Long names truncate; amounts stay on one line.
- [ ] Bar and moved/returned lines render as before.
- [ ] No `button` inside `a` in the DOM.
- [ ] Keyboard: one tab stop for normal cards, two for editable over-budget
      cards.
- [ ] `pnpm types:check` passes once copied into place.

## Verification performed in this scratch run

- Draft type-checks against the repo config (temporary config extending
  `tsconfig.json`, including only the draft, deleted afterwards):

  ```bash
  pnpm exec tsc --noEmit -p .scratch/.tsconfig-t3.json
  ```

- Formatting and class sorting:

  ```bash
  pnpm exec vp check .scratch/T3-budget-item.tsx
  ```

  `pass: All 1 file are correctly formatted`; no lint warnings.

The repo `tsconfig.json` only includes `resources/js/**`, so the draft is not
part of `pnpm types:check` until copied into place.

## Constraints honoured

- Only `.scratch/` files were created/modified; `resources/js/` is read-only.
- No backend, types, routes, or tests changed (layout-only).
- `.scratch/` is gitignored (`.gitignore` line `/.scratch`), so the commit
  force-adds the two T3 scratch files explicitly.
