Implement the new design across the whole app. Reference files are in `design/` (open the .dc.html files in a browser; each has Light/Dark toggles and page pickers):
- `app-css-contrast-patch.css`: color tokens (light + dark)
- `WIBS Portal Pages.dc.html`: Login, Overview (banking style), Loans, Loan Security, Loan requests, Settings (vertical section nav, no horizontal tabs)
- `WIBS Staff Pages.dc.html`: Loan Processor and Loan Manager (dashboard, workflow, request review, reported requests, members, reports)
- `WIBS All Pages.dc.html`: every other page (auth flows, error, welcome, schedule, payments, request detail, notifications, security settings, admin, member admin pages, organization settings, staff management, audit log)
- `WIBS Loan Wizard.dc.html`: the loan request wizard restyled

Work in phases. After each phase run `npm run build` and lint, fix errors, make ONE commit, then stop and summarize. Wait for me to say "continue" before the next phase. Use theme variables and Tailwind tokens only; no new hard-coded hex in components. Do not change behavior, routes, props, validation or data.

## Phase 1: tokens and shell
1. resources/css/app.css: replace the `:root` and `.dark` blocks with design/app-css-contrast-patch.css. Keep existing `--chart-*` and `--radius` lines.
2. Check resources/js/theme/* (branding-theme.ts, inject-theme.ts, clients/mrdinc.ts): the runtime injector must NOT set --background, --card, --sidebar or other surface tokens.
3. Sidebar: resources/js/components/app-sidebar.tsx uses `variant="inset"`, which draws floating rounded cards. Change to `variant="sidebar"`. In ui/sidebar.tsx the sidebar variant must be full height, fixed left, `border-r border-sidebar-border`, no outer padding or rounding. In app-content.tsx remove inset-only styling (margin, rounded-xl, shadow). Only the main content scrolls; the sidebar stays fixed and scrolls internally.
4. Header bar (app-sidebar-header.tsx): 56px, `border-b border-border`, `bg-background`, with a small "Secure session" pill on the right (tint background, primary dot).
5. Nav (nav-main, nav-user, app-logo): `text-sidebar-foreground`; active = `bg-sidebar-accent text-sidebar-accent-foreground font-semibold`; hover = `hover:bg-sidebar-accent/25`; group labels = `text-sidebar-foreground/75`.
6. Replace hard-coded `bg-white`, `bg-neutral-*`, `bg-gray-*`, `dark:bg-black`, hex colors in layouts/components with tokens.

## Phase 2: shared components
- ui/button: primary = deep green + white; accent = lime + dark text; radius 8px (no pill buttons).
- ui/card: `bg-card border-border`, radius 12px, subtle shadow (`shadow-md`, slightly stronger in dark mode). Design default is a soft shadow; the raised option is `shadow-lg`.
- ui/badge: secondary = `bg-secondary text-secondary-foreground` with a visible border. Status badge colors (green/amber/blue/rose/gray) must have text contrast of at least 4.5:1 in light and dark.
- ui/input, select, textarea: `border-input bg-card`, visible focus ring.
- ui/table: header row `bg-muted`, uppercase 11px tracking-wide muted labels, right-aligned tabular-nums for amounts.
- PageHero / PageShell / SurfaceCard: primary-colored hero band where the design uses one; cards per the above.

## Phase 3: member pages (design/WIBS Portal Pages.dc.html)
Restyle only: auth/login.tsx (split layout: deep-green brand panel left, form card right), client/dashboard.tsx (banking-style: last-login line, account cards for Loan account and Loan security with masked numbers, Hide/Show balances toggle, recent transactions list with in/out amounts, security notice, account holder card), client/loans.tsx, client/savings.tsx, client/loan-requests.tsx, settings/profile.tsx (replace horizontal tabs with a LEFT vertical section nav: Account, Personal, Work, Bank, Dependents, each with a one-line description; content on the right). Reuse existing components and data.

## Phase 4: loan wizard (design/WIBS Loan Wizard.dc.html)
Keep resources/js/pages/client/loan-request.tsx logic, steps, validation and structure exactly. Restyle only: loan-request-wizard-shell, loan-request-step-indicator (tinted sidebar, active group highlighted, sub-steps with dots, progress bar), loan-request-section-card, loan-request-summary-panel, loan-request-wizard-footer, loan-request-page-sections, page header card. Summary panel wraps under the form on narrow widths.

## Phase 5: staff pages (design/WIBS Staff Pages.dc.html and WIBS All Pages.dc.html)
staff/processor-dashboard.tsx, staff/loan-requests.tsx, staff/loan-request-show.tsx (role-specific actions unchanged), staff/reported-requests.tsx, staff/members.tsx, admin/reports.tsx, admin/dashboard.tsx, admin/watchlist.tsx (members), admin/member-*.tsx, admin/requests.tsx, admin/reported-requests.tsx, admin/organization-settings.tsx, superadmin/staff.tsx, superadmin/audit-log.tsx.

## Phase 6: remaining pages
auth/* (register, forgot-password, reset-password, verify-email, verify-member, pending-approval, two-factor-challenge, confirm-password) using the split auth layout; errors/error.tsx; welcome.tsx; notifications.tsx; client/loan-schedule.tsx, loan-payments.tsx, loan-request-show.tsx; settings/password.tsx, security.tsx, two-factor.tsx, appearance.tsx.

## Phase 7: verify
Check every page in light and dark mode. The sidebar must be a solid green full-height column touching the screen edge with no white gap. Report any text under 4.5:1 contrast and any page you could not restyle.
