# CLAUDE.md

> Quick-ref for project-specific structure only. Generic conventions → AGENTS.md.

## Project Map

| Concern | Path |
|---|---|
| Controllers (Inertia) | `app/Http/Controllers/Spa/{Admin,Staff,Superadmin}/` |
| Controllers (JSON) | `app/Http/Controllers/{Admin,Client,Staff,Superadmin}/` |
| Services | `app/Services/{Admin,Auth,LoanRequests,Locations,Notifications,Reports,Sms}/` |
| Form Requests | `app/Http/Requests/{Admin,Client,Staff,Superadmin,Workflow,Spa}/` |
| Enums | `app/Loan*.php` — app root, **not** `app/Enums/` |
| Auth model | `App\Models\AppUser` — never `User` |
| Pages | `resources/js/pages/{admin,auth,client,staff,superadmin}/` |
| Types | `resources/js/types/*.ts` |
| JSON API clients | `resources/js/lib/api/{admin,client,notifications}.ts` |
| Wayfinder output | `resources/js/routes/{group}/index.ts` |

## Key Patterns

- Inertia navigations → Wayfinder (`resources/js/routes/`)
- JSON/AJAX calls → `resources/js/lib/api/` (never Wayfinder)
- Workflow transitions → create a `LoanRequestChange` audit entry
- Drafts → must **not** create audit entries
- Conflict of interest → applicant cannot process or approve their own request
- Notifications → use `LoanRequestNotificationService::EVENT_*` constants

## Workflow Status Flow

**Path:** `draft` → `pending_co_maker_signatures` → `submitted` → `pending_review` → `under_review` → `recommended_for_approval` → `awaiting_member_acceptance` → `approved` → `converted_to_loan` → `for_wibs_encoding` → `wibs_loan_created` → `release_scheduled` → `released`
**Revision loops:** `under_review` → `needs_revision` | `awaiting_member_information` → back to `under_review`
**Exits (terminal):** `rejected` | `declined` | `member_declined_terms` | `cancelled`

## Roles & Permissions

| Constant | Value |
|---|---|
| `Role::MEMBER` | `member` |
| `Role::LOAN_PROCESSOR` | `loan_processor` |
| `Role::LOAN_MANAGER` | `loan_manager` |
| `Role::ADMIN` | `admin` |
| `Role::SUPERADMIN` | `superadmin` |

## Commands

```
loan-workflow:preflight            Pre-deploy readiness check
loan-workflow:deployment-check     Post-deploy verification
loan-workflow:seed-permissions     Seed roles and permissions
loan-workflow:repair               Fix broken workflow state
loan-workflow:smoke-test           End-to-end smoke test
loan-workflow:send-reminders       Send pending notifications
loan-workflow:cleanup-temp-files   Remove temp export files
loan-requests:repair-owners        Fix orphaned request owners
loan-requests:backfill-health-fields  Backfill health_smoking_status; report item 2e rows for review
```

## UI Development Guidelines

- Prioritize simple, clean, and user-friendly interfaces.
- Review the workflow before changing UI.
- Reduce unnecessary information and use progressive disclosure when appropriate.
- Prefer shadcn/ui components over custom UI implementations.
- Maintain consistency in spacing, layout, and component usage.
- Refactor components when needed to keep the codebase maintainable.
- Consider accessibility and usability.

## Hard Rules

- `AppUser` everywhere — never `User`
- Enums live in `app/Loan*.php` (app root), not `app/Enums/`
- All validation through Form Requests — never inline `$request->validate()`
- Eloquent aggregates only — no `DB::raw()` for counts/sums
- Check sibling files before creating a new one
- Run `vendor/bin/pint --dirty` before finishing
- Every change needs a Pest test

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
