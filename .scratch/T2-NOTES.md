# T2 — Minimal horizontal extension on ScrollArea (scratch draft)

Status: draft only — the real edit lands later in
`resources/js/components/ui/scroll-area.tsx` under the scratch-only constraint.

## What changed from T1

`.scratch/T2-scroll-area.tsx` is `.scratch/T1-scroll-area.tsx` plus exactly three
optional props on the `ScrollArea` root:

```tsx
type ScrollAreaProps = ScrollAreaPrimitive.Root.Props & {
  orientation?: "vertical" | "horizontal"
  scrollBarClassName?: string
  viewportClassName?: string
}
```

- `orientation` defaults to `"vertical"` and is forwarded to `<ScrollBar>`; the
  default output is identical to the generated `<ScrollBar />` (vertical bar,
  same classes).
- `scrollBarClassName` is merged into the `ScrollBar` classes via `cn()`, so the
  hidden-until-hover override works without editing the file again.
- `viewportClassName` is merged into the `Viewport` classes via `cn()`. It is
  unused by T4 but kept as part of the agreed minimal seam.
- A short comment marks the block as a deliberate fork so `shadcn diff` merges
  stay obvious.
- No snap logic, no new JS, no behavioural change for vertical callers.
  `ScrollBar` itself is unchanged from T1.

## Usage example (T4 / BudgetList)

```tsx
<ScrollArea
    orientation="horizontal"
    scrollBarClassName="opacity-0 data-hovering:opacity-100 data-scrolling:opacity-100"
    className="-mx-4"
>
    <div className="flex items-stretch gap-3 px-4 pb-2">
        {budgets.map((b) => (
            <BudgetItem key={b.id} b={b} base={base} canEdit={canEdit} onMoveMoney={onMoveMoney} />
        ))}
    </div>
</ScrollArea>
```

Base UI's `Scrollbar` supplies `data-hovering` while hovered and
`data-scrolling` while scrolling, so the classes above hide the bar by default
and reveal it only during desktop interaction. On touch there is no persistent
bar; the card peek is the affordance.

## Acceptance (ticket T2)

- [ ] `<ScrollArea orientation="horizontal" />` renders a horizontal bar.
- [ ] Default (no `orientation`) renders a vertical bar, byte-identical
      classes to the generated file.
- [ ] `scrollBarClassName="opacity-0 data-hovering:opacity-100 data-scrolling:opacity-100"`
      hides the bar until hover/scroll.
- [ ] `viewportClassName` reaches the Viewport and merges with the defaults.
- [ ] `pnpm check` / `pnpm types:check` pass once copied into
      `resources/js/components/ui/` (that directory is excluded from the repo's
      lint/format ignore lists only as generated code; class sorting still
      applies by convention).

## Verification performed in this scratch run

- Standalone TypeScript check of the draft:

  ```bash
  pnpm exec tsc --noEmit --strict --jsx react-jsx --module ESNext \
    --moduleResolution bundler --target ESNext --skipLibCheck \
    --esModuleInterop .scratch/T2-scroll-area.tsx
  ```

  (The repo `tsconfig.json` only includes `resources/js/**`, so scratch files are
  not part of `pnpm types:check` until copied into place.)

## Constraints honoured

- Only `.scratch/` files were created/modified; source is read-only.
- No backend, types, routes, or tests changed (layout-only).
- `.scratch/` is gitignored (`.gitignore` line `/.scratch`), so this commit
  force-adds the scratch files explicitly.
