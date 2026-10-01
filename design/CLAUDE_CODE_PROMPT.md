Apply the new design system to the whole app. Reference files are in `design/`:
- `app-css-contrast-patch.css` (color tokens, light + dark)
- `WIBS Member Dashboard Contrast.dc.html` (visual reference: open it in a browser, use the Before / After · light / After · dark buttons)

## Goal
The deployed app looks almost all white in light mode and too dark in dark mode. Make surfaces clearly contrasted using the org palette (deep green #176433, lime #9abd53).

## Steps (do in order; run `npm run build` and lint after each; stop and summarize between steps)

1. **Tokens.** Replace the `:root` and `.dark` blocks in `resources/css/app.css` with `design/app-css-contrast-patch.css`. Keep everything else in the file.

2. **Find overrides.** Search `resources/js/theme/*` (branding-theme, inject-theme, clients/mrdinc) and the layouts for anything that overwrites these CSS variables at runtime or hard-codes colors (`bg-white`, `bg-background`, `bg-neutral-*`, `bg-gray-*`, `text-gray-*`, hex values, `dark:bg-black`). Point the theme injector at the same palette and replace hard-coded colors with theme tokens (`bg-background`, `bg-card`, `bg-muted`, `text-muted-foreground`, `border-border`, `bg-primary`, `bg-accent`, `bg-sidebar`).

3. **Layout surfaces (all pages, admin and client).**
   - Page background = `bg-background` (sage in light, dark green in dark, never white or black).
   - Cards = `bg-card` with `border-border`; nested tiles inside cards = `bg-muted`.
   - Sidebar = `bg-sidebar` (solid deep green in light) with `text-sidebar-foreground`; active item = lime `bg-sidebar-accent text-sidebar-accent-foreground`; user box uses a slightly darker green.
   - Secondary text uses `text-muted-foreground` (already ≥4.5:1).

4. **Shared components.** Update `ui/button`, `ui/badge`, `ui/card`, `ui/input` so: primary button = deep green + white text; accent button = lime + dark text; badges (ACTIVE, Posted, Approved) = `bg-secondary text-secondary-foreground` with a visible border; inputs use `border-input`; corners 8-12px (cards 12px, buttons 8px, no fully-round buttons).

5. **Client dashboard** (`resources/js/pages/client/dashboard.tsx`): match the reference: "Hi, {name}" greeting with ACTIVE badge; deep-green Outstanding balance card (large tabular figures, lime progress bar, lime "View loans" button); white Loan security card with a green "View loan security" button; tabular-nums on all amounts; right-aligned amounts in transaction lists.

6. **Verify.** Check every page in light and dark mode. Flag any text below 4.5:1 contrast. List any screens you could not fix.

Rules: use theme variables/Tailwind tokens, no new hard-coded hex in components. Do not change behavior, routes or data. Make one commit per step.
