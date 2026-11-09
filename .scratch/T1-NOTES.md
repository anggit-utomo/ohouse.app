# T1 — Add shadcn ScrollArea (scratch draft)

Status: draft only — the real file lives in `resources/js/components/ui/` and was
not written in this run because of the scratch-only constraint.

## What the real T1 does

```bash
pnpm dlx shadcn@latest add @shadcn/scroll-area
```

That command reads `components.json` (`"style": "base-mira"`) and writes:

- `resources/js/components/ui/scroll-area.tsx`

It must not be replaced with a hand-written component in the real ticket; the
command is the source of truth and keeps `shadcn diff` mergeable.

## Proposed file

`.scratch/T1-scroll-area.tsx` is the proposed generated output for the
`base-mira` (Base UI) registry item:

- Base UI import: `@base-ui/react/scroll-area` (`^1.9.0`, already a dependency).
- `cn` import follows every other generated `resources/js/components/ui/*` file
  in this repo (`import { cn } from "cn"`), **not** `@/lib/utils`.
- Exports `ScrollArea` and `ScrollBar`.
- Uses Base UI parts `Root`, `Viewport`, `Scrollbar`, `Thumb`, `Corner`.
- Children render directly inside `Viewport` (no `Content` wrapper), matching
  the generated base-mira shape described in the spec.
- Renders a vertical `ScrollBar` by default. The generated `ScrollBar` already
  accepts Base UI's `orientation` prop; T2 adds the `ScrollArea` root
  `orientation` prop plus the class-name pass-throughs. No extension is
  included here.
- Uses the repo's `shadcn/tailwind.css` variants: `data-horizontal:` /
  `data-vertical:` map to `data-orientation`. Base UI supplies the state
  attributes (`data-hovering`, `data-scrolling`) used by T4's override.

## How to apply for real

1. Run the command above (requires network and writes into `resources/js`).
2. Diff the result against `.scratch/T1-scroll-area.tsx`; the generated file
   wins if shadcn's current registry output differs.
3. Run `pnpm types:check`.

## Acceptance (ticket T1)

- [ ] `resources/js/components/ui/scroll-area.tsx` exists.
- [ ] It exports `ScrollArea` and `ScrollBar`.
- [ ] It imports from `@base-ui/react/scroll-area`.
- [ ] `pnpm types:check` passes.

## Verification performed in this scratch run

- Standalone TypeScript check of the draft:

  ```bash
  pnpm exec tsc --noEmit --strict --jsx react-jsx --module ESNext \
    --moduleResolution bundler --target ESNext --skipLibCheck \
    --esModuleInterop .scratch/T1-scroll-area.tsx
  ```

  (The repo `tsconfig.json` only includes `resources/js/**`, so the scratch file
  is not part of `pnpm types:check` until it is copied into place.)

## Constraints honoured

- No source files outside `.scratch/` were modified.
- No `pnpm dlx shadcn` / installers were run.
- `.scratch/` is gitignored (`.gitignore` line `/.scratch`), so this commit
  force-adds the two scratch files explicitly.
