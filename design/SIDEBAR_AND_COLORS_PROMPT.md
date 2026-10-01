Two fixes so the app matches the design in `design/` (open `WIBS Portal Pages.dc.html` for reference).

## 1. Sidebar: flush, full-height, not a floating card
Cause: `resources/js/components/app-sidebar.tsx` uses `<Sidebar collapsible="icon" variant="inset">`. The inset variant wraps the sidebar in padding and rounds the content area into a card.

- Change it to `variant="sidebar"`.
- In `resources/js/components/ui/sidebar.tsx`, confirm the `sidebar` variant renders: full viewport height (`h-svh`), fixed to the left, `border-r border-sidebar-border`, no outer padding, no rounded corners.
- `AppContent variant="sidebar"` renders `SidebarInset`. Remove any inset-only styling from it (`md:peer-data-[variant=inset]:m-2`, `rounded-xl`, `shadow`) so the main area is a flat full-height column. Keep `overflow-x-hidden`.
- Only the main content scrolls. The sidebar stays fixed; long menus scroll inside it.
- Header bar (`app-sidebar-header.tsx`): 56px tall, `border-b border-border`, `bg-background`.

## 2. Colors: the new tokens are not in the repo yet
`resources/css/app.css` still has the FIRST palette (`--background: #f3f2ea`, `--sidebar: #ffffff`). That is why the deployed app is white with no contrast.

- Replace the `:root` and `.dark` blocks with `design/app-css-contrast-patch.css`. Keep `--chart-*` and `--radius` lines from the current file.
- Result: sage page (#dfe7cb), white cards, DEEP GREEN sidebar (#176433) with white text and a lime active item; dark mode uses green-tinted surfaces, not near-black.
- Sidebar nav (`nav-main.tsx`, `nav-user.tsx`, `app-logo.tsx`): use `text-sidebar-foreground`, active = `bg-sidebar-accent text-sidebar-accent-foreground font-semibold`, hover = `hover:bg-sidebar-accent/25`. Muted labels ("Member Portal", "Staff Workspace") use `text-sidebar-foreground/75`, not `opacity` below that (keep 4.5:1).
- In `resources/js/theme/branding-theme.ts`, keep `sidebar-primary`, `sidebar-ring` and surface tokens CSS-owned in light mode (they already are). Confirm the runtime injector does not set `--sidebar`, `--background` or `--card`.
- Search for hard-coded `bg-white`, `bg-neutral-*`, `bg-gray-*`, `dark:bg-black` in layouts and replace them with theme tokens.

## Verify
Run build + lint. Check Overview, Loans, Loan requests and Settings in light and dark. The sidebar must be a solid green full-height column touching the screen edges, with no white gap or rounded card around it.
