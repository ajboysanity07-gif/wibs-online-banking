repo: ajboysanity07-gif/wibs-online-banking
branch: main

## Last sync
date: 2026-09-29T11:43:00Z

### Updated in this project
- WIBS Portal Pages: Login, Overview, Loans, Loan Security, Loan requests, Settings (light + dark)
- WIBS Staff Pages: Loan Processor + Loan Manager (dashboard, workflow, review, reported requests, members, reports)
- WIBS Loan Wizard: same 12-step wizard restyled to the new theme
- Palette tokens confirmed against app.css and theme/clients/mrdinc.ts

## Screen map
| Project screen | Repo files |
|---|---|
| WIBS Portal Pages.dc.html (Login) | resources/js/pages/auth/login.tsx |
| (Overview) | resources/js/pages/client/dashboard.tsx |
| (Loans) | resources/js/pages/client/loans.tsx |
| (Loan Security) | resources/js/pages/client/savings.tsx |
| (Loan requests) | resources/js/pages/client/loan-requests.tsx |
| (Settings) | resources/js/pages/settings/profile-shared.tsx |
| WIBS Loan Wizard.dc.html | resources/js/pages/client/loan-request.tsx, components/loan-request/loan-request-wizard-steps.ts, -wizard-shell.tsx, -step-indicator.tsx, -wizard-footer.tsx, -summary-panel.tsx, -section-card.tsx |
| WIBS Staff Pages.dc.html | resources/js/pages/staff/processor-dashboard.tsx, staff/members.tsx, staff/loan-requests.tsx, staff/loan-request-show.tsx, staff/reported-requests.tsx, admin/reports.tsx, components/app-sidebar.tsx |
| WIBS Member Dashboard Contrast.dc.html | resources/js/pages/client/dashboard.tsx, resources/css/app.css |
