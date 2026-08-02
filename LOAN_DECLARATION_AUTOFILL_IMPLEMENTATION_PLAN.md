# Implementation Plan: Auto-populate Loan Declarations + PDL/IIL Visibility

## Summary of Requirements

### Member-Facing (Client Portal)
1. Auto-fill `declaration_existing_loans` (any loan exists in `wlnmaster`, regardless of balance)
2. Auto-fill `declaration_pending_cases` (only when `lnstatus = 'IIL'`)
3. Auto-fill slot 1 with most recent loan details (`date_rel`, `lntype`, `principal`)
4. Silent pre-fill, member can change values
5. Show member their own loan statuses on their dashboard

### Staff-Facing (Processor/Admin)
1. Show PDL/IIL information on loan request detail/review page
2. Detailed list with loan numbers, amounts, dates, status
3. Soft warning (does not block approval, processor's discretion)
4. Log that processor saw the warning (audit trail)

---

## Data Source: `wlnmaster` Table (WIBS)

Key columns used:

| Column | Purpose |
|---|---|
| `acctno` | Links to member's account (matches `AppUser.acctno`) |
| `lnnumber` | Loan number (primary key) |
| `lntype` | Loan type (e.g. Personal, Business) |
| `lnstatus` | **ACT** = Active, **PDL** = Past Due, **IIL** = In Litigation |
| `principal` | Original loan amount |
| `balance` | Current outstanding balance |
| `date_rel` | Loan release/grant date |
| `date_mat` | Maturity date |
| `lastmove` | Last transaction date (fallback for ordering) |

### Logic Summary

- `declaration_existing_loans` → `true` if ANY record exists for `acctno` (balance ignored)
- `declaration_pending_cases` → `true` if any record has `lnstatus = 'IIL'`
- Slot 1 auto-fill → most recent loan by `date_rel DESC`:
  - `existing_loan_1_type` → `lntype`
  - `existing_loan_1_amount` → `principal`
  - `existing_loan_1_date` → `date_rel` (formatted `Y-m-d`)

---

## Tasks

### Task 1: Update `Wlnmaster` Model

**File:** `app/Models/Wlnmaster.php`

Add to `$fillable`:
```php
'lnstatus',
'date_rel',
'date_mat',
```

Add to `casts()`:
```php
'date_rel' => 'date',
'date_mat' => 'date',
```

---

### Task 2: Create `LoanDeclarationAutoFillService`

**File:** `app/Services/LoanRequests/LoanDeclarationAutoFillService.php` (NEW)

Constructor injects `SchemaCapabilities` (check `app/Support/SchemaCapabilities.php` for exact namespace — it is used as `App\Support\SchemaCapabilities` in other services; verify before writing).

**Methods:**

1. **`getDeclarationData(AppUser $user): array`** — member form auto-fill
   ```php
   return [
       'declaration_existing_loans' => bool,
       'declaration_pending_cases' => bool,
       'existing_loan_1_date' => ?string,  // Y-m-d
       'existing_loan_1_type' => ?string,  // lntype
       'existing_loan_1_amount' => ?float, // principal
   ];
   ```

2. **`getLoanStatusSummaryForStaff(string $acctno): array`** — staff review warning
   ```php
   return [
       'has_active' => bool,
       'has_past_due' => bool,
       'has_litigation' => bool,
       'total_active' => int,
       'total_past_due' => int,
       'total_litigation' => int,
       'active_balance_total' => float,
       'past_due_balance_total' => float,
       'litigation_balance_total' => float,
       'requires_attention' => bool,   // true if PDL or IIL > 0
       'warning_message' => ?string,   // e.g. "Applicant has 1 past due loan(s) and 1 loan(s) in litigation"
   ];
   ```

3. **`getProblemLoans(string $acctno): array`** — detailed PDL/IIL loan list
   ```php
   return [
       [
           'lnnumber' => string,
           'lntype' => string,
           'lnstatus' => string,          // PDL or IIL
           'lnstatus_label' => string,    // 'Past Due' | 'In Litigation'
           'principal' => float,
           'balance' => float,
           'date_rel' => ?string,
           'date_mat' => ?string,
       ],
   ];
   ```
   Query: `where('acctno')->whereIn('lnstatus', ['PDL', 'IIL'])`, order IIL first then `date_rel DESC`.

4. **`getLoanStatusSummaryForMember(string $acctno): array`** — member dashboard
   ```php
   return [
       'total_loans' => int,
       'active_count' => int,
       'past_due_count' => int,
       'litigation_count' => int,
       'total_balance' => float,
       'active_balance' => float,
       'past_due_balance' => float,
       'litigation_balance' => float,
       'loans' => [ /* per loan: lnnumber, lntype, lnstatus, lnstatus_label, principal, balance, date_rel, date_mat */ ],
   ];
   ```

**Private helpers:**
- `hasExistingLoans(string $acctno): bool` — `Wlnmaster::where('acctno', $acctno)->exists()`
- `hasLitigationLoans(string $acctno): bool` — `where('acctno')->where('lnstatus', 'IIL')->exists()`
- `getMostRecentLoanDetails(string $acctno): ?array` — order by `date_rel DESC`, fallback to `lastmove DESC`
- `resolveDateColumn(): string` — `date_rel` → `lastmove` → `lnnumber` (schema-guarded)
- `getLoanStatusLabel(string $status): string` — ACT → 'Active', PDL → 'Past Due', IIL → 'In Litigation'
- Empty-data builders for all four return shapes

**Schema guards (critical for production safety):**
- Check `schemaCapabilities->hasTable('wlnmaster')` before any query → return empty data
- Check `hasColumn('wlnmaster', 'lnstatus')` before IIL queries → `pending_cases` false
- Check `hasColumn('wlnmaster', 'date_rel')` → fallback to `lastmove`
- Check `$user->acctno !== null && trim !== ''` → else return empty data

---

### Task 3: Integrate Auto-fill into `LoanRequestService::getFormData()`

**File:** `app/Services/LoanRequests/LoanRequestService.php`

1. Inject `LoanDeclarationAutoFillService` in constructor.
2. In `getFormData()` (around line 87), after `$draft = $this->getActiveEditableRequest($user);`:
   ```php
   $autoFilledDeclarations = [];
   if ($draft === null) {
       $autoFilledDeclarations = $this->declarationAutoFillService->getDeclarationData($user);
   }
   ```
3. Add `'autoFilledDeclarations' => $autoFilledDeclarations` to the returned payload.

**Key behavior:** only auto-fill on fresh forms (no draft). Never override saved draft values.

---

### Task 4: Integrate Loan Status into Staff/Admin Payload

**File:** `app/Services/LoanRequests/LoanRequestPayloadSerializer.php`

Find the serializer method used by staff/admin loan request detail pages. Add:

```php
'applicant_loan_status' => $loanStatus, // null when no acctno or no wlnmaster
```

Where `$loanStatus` is built from `getLoanStatusSummaryForStaff($acctno)` merged with:
```php
'problem_loans' => $summary['requires_attention']
    ? $this->loanDeclarationAutoFillService->getProblemLoans($acctno)
    : [],
```

**IMPORTANT:** Locate all serializer methods (staff view, admin view, workflow views) and the applicant acctno resolution helper first. Verify how the applicant's acctno is stored on `LoanRequest` (via `loanRequestPerson` with role applicant? processing data?) before wiring.

---

### Task 5: Create `LoanStatusWarning` Component (Staff)

**File:** `resources/js/components/loan-request/loan-status-warning.tsx` (NEW)

Props: `loanStatus` (the `applicant_loan_status` payload).

UI:
- Red destructive alert banner when `requires_attention` is true
- Shows `warning_message` text
- Counts + total balances for PDL and IIL
- Expandable/collapsible detail list of problem loans (loan number, type, status badge, released/maturity dates, principal, balance)
- Footnote: "Soft warning — you may approve or decline based on your assessment and organizational policies."
- Renders `null` when no problem loans

Use existing UI components from `resources/js/components/ui/` (alert, badge, card) and `lucide-react` icons (AlertTriangle, ChevronDown, ChevronUp). Follow existing component conventions in the repo.

---

### Task 6: Create `MemberLoanStatusCard` Component

**File:** `resources/js/components/member/member-loan-status-card.tsx` (NEW)

Props: `loanSummary` (member summary payload).

UI:
- "Your Loans" card with total count + total balance
- Summary stats grid: Active / Past Due / In Litigation counts + balances (colored: orange PDL, red IIL)
- Loan list with status badges (ACT → default, PDL → secondary/orange, IIL → destructive/red)
- Warning box when PDL/IIL present: "Please contact our office to discuss payment arrangements."
- Empty state: "You currently have no active loans"

---

### Task 7: Update Member Loan Request Form

**File:** `resources/js/pages/client/loan-request.tsx`

Add `autoFilledDeclarations` to the page props type and merge into the initial `form.data.declarations`:

```ts
declaration_existing_loans: draft?.declarations?.declaration_existing_loans
    ?? autoFilledDeclarations?.declaration_existing_loans
    ?? false,
// same pattern for declaration_pending_cases, existing_loan_1_date/type/amount
```

Priority: draft data > auto-filled data > defaults. Also update the page props interface in the same file (or in the shared types file).

---

### Task 8: Add Warning to Staff Review Page

**File:** `resources/js/pages/staff/...` (locate the staff loan request detail/review page — likely under `resources/js/pages/staff/`)

- Render `<LoanStatusWarning loanStatus={loanRequest.applicant_loan_status} />` near the top of the page, only when the prop exists.

**Audit log on view (Task 10):** add `useEffect` that fires once per request view when `requires_attention` is true, calling a POST route (e.g. `staff.loan-requests.log-warning-viewed`). `preserveState` + `preserveScroll` to avoid full reload.

---

### Task 9: Add Loan Status to Member Dashboard

**File:** `app/Http/Controllers/Client/...` (locate the client dashboard controller) + `resources/js/pages/client/dashboard.tsx`

- Controller: pass `'loanSummary' => $service->getLoanStatusSummaryForMember($user->acctno)` (null if no acctno).
- Page: render `<MemberLoanStatusCard loanSummary={loanSummary} />` when present.

---

### Task 10: Audit Log for Warning Views

**Option A (preferred):** Create a `LoanRequestChange` record (audit trail — see AGENTS.md: workflow transitions create audit entries; follow existing pattern in `app/Models/LoanRequestChange.php` / any audit-writing service).

Fields:
- `loan_request_id`
- `user_id` (staff)
- `change_type` → e.g. `'system_note'` (verify existing enum/values)
- `field_name` → `'loan_status_warning_viewed'`
- `new_value` → JSON: `{has_past_due, has_litigation, warning_message, viewed_at}`
- `reason` → 'Staff viewed loan request with problematic loans (PDL/IIL)'

**Backend:** add `logWarningViewed` method to `app/Http/Controllers/Staff/LoanRequestController.php` (or the appropriate staff controller) + route in the staff routes file.

Check for an existing audit service (e.g. `LoanRequestAuditService` or similar) before creating a new one. If `LoanRequestChange` has strict rules, mirror an existing write site.

---

### Task 11: TypeScript Type Definitions

**File:** `resources/js/types/index.ts` (or existing shared types file)

```ts
export interface AutoFilledDeclarations {
    declaration_existing_loans?: boolean;
    declaration_pending_cases?: boolean;
    existing_loan_1_date?: string | null;
    existing_loan_1_type?: string | null;
    existing_loan_1_amount?: number | null;
}

export interface ProblemLoan {
    lnnumber: string;
    lntype: string;
    lnstatus: string;
    lnstatus_label: string;
    principal: number;
    balance: number;
    date_rel: string | null;
    date_mat: string | null;
}

export interface LoanStatusSummaryForStaff {
    has_active: boolean;
    has_past_due: boolean;
    has_litigation: boolean;
    total_active: number;
    total_past_due: number;
    total_litigation: number;
    active_balance_total: number;
    past_due_balance_total: number;
    litigation_balance_total: number;
    requires_attention: boolean;
    warning_message: string | null;
    problem_loans: ProblemLoan[];
}

export interface LoanStatusSummaryForMember {
    total_loans: number;
    active_count: number;
    past_due_count: number;
    litigation_count: number;
    total_balance: number;
    active_balance: number;
    past_due_balance: number;
    litigation_balance: number;
    loans: Array<Omit<ProblemLoan, 'principal'> & { principal: number }>;
}
```

---

## Testing Strategy

### Unit Tests

**File:** `tests/Unit/Services/LoanRequests/LoanDeclarationAutoFillServiceTest.php` (NEW)

1. No loans → all false/null
2. 1 ACT loan → existing=true, cases=false, slot 1 filled
3. 1 IIL loan → both true, slot 1 filled
4. 1 PDL loan → existing=true, cases=false
5. 3 loans → picks most recent by `date_rel`
6. No acctno → empty data
7. `wlnmaster` table missing → empty data
8. `lnstatus` column missing → pending_cases=false, existing still works
9. `date_rel` missing → fallback to `lastmove`
10. Staff summary counts ACT/PDL/IIL correctly
11. Staff summary sums balances correctly
12. `requires_attention` true when PDL or IIL present
13. `warning_message` formatting
14. `getProblemLoans` returns only PDL/IIL
15. `getProblemLoans` sort order (IIL first, then date_rel DESC)
16. Member summary complete with all loans
17. Member summary categorizes loans by status

### Feature Tests

**File:** `tests/Feature/Client/LoanRequestDeclarationAutoFillTest.php` (NEW)

1. GET `/loan-requests/create` includes `autoFilledDeclarations`
2. IIL loan → both declarations true in payload
3. ACT loan → only existing_loans true
4. Member can override auto-filled values and submit
5. Existing draft not overridden (autoFilledDeclarations empty with draft)
6. Auto-fill uses `date_rel` for most-recent sort
7. Multiple loans → most recent wins for slot 1
8. Graceful when `wlnmaster` missing

**File:** `tests/Feature/Staff/LoanRequestStaffVisibilityTest.php` (NEW)

1. Staff sees warning banner with PDL/IIL
2. No warning without PDL/IIL
3. Problem loan list shows correct details
4. Viewing with PDL/IIL creates audit log entry
5. Summary shows correct counts and balances
6. Staff can still approve/decline (soft warning)

**File:** `tests/Feature/Client/MemberDashboardLoanStatusTest.php` (NEW)

1. Dashboard shows loan status card
2. No loans → "no active loans" message
3. PDL/IIL → warning message shown
4. Loan list with correct status badges

---

## Files Summary

### Create
1. `app/Services/LoanRequests/LoanDeclarationAutoFillService.php`
2. `resources/js/components/loan-request/loan-status-warning.tsx`
3. `resources/js/components/member/member-loan-status-card.tsx`
4. `tests/Unit/Services/LoanRequests/LoanDeclarationAutoFillServiceTest.php`
5. `tests/Feature/Client/LoanRequestDeclarationAutoFillTest.php`
6. `tests/Feature/Staff/LoanRequestStaffVisibilityTest.php`
7. `tests/Feature/Client/MemberDashboardLoanStatusTest.php`

### Modify
1. `app/Models/Wlnmaster.php` — add `lnstatus`, `date_rel`, `date_mat`
2. `app/Services/LoanRequests/LoanRequestService.php` — inject service, auto-fill in `getFormData()`
3. `app/Services/LoanRequests/LoanRequestPayloadSerializer.php` — add `applicant_loan_status`
4. `app/Http/Controllers/Staff/LoanRequestController.php` — `logWarningViewed` method
5. `app/Http/Controllers/Client/DashboardController.php` (locate) — pass `loanSummary`
6. `resources/js/pages/client/loan-request.tsx` — merge auto-fill in form init
7. `resources/js/pages/staff/loan-request-*.tsx` (locate) — render `LoanStatusWarning` + audit useEffect
8. `resources/js/pages/client/dashboard.tsx` — render `MemberLoanStatusCard`
9. `resources/js/types/index.ts` — new type definitions
10. Routes file (staff) — `log-warning-viewed` POST route

---

## Behavior Matrix

### Member Experience

| Scenario | Form Auto-fill | Dashboard Display |
|---|---|---|
| No loans | Both false | "No active loans" |
| 1 ACT loan | existing=true, cases=false, slot 1 filled | 1 active loan |
| 1 PDL loan | existing=true, cases=false, slot 1 filled | 1 past due + warning |
| 1 IIL loan | both true, slot 1 filled | 1 litigation + warning |
| Multiple loans | depends on statuses, slot 1 = most recent | All loans grouped by status |
| Has draft | No auto-fill (draft values used) | Current loans |

### Staff Experience

| Applicant Status | Warning | Action |
|---|---|---|
| No PDL/IIL | None | Normal review |
| PDL only | Yellow warning + details | Soft — can approve |
| IIL only | Red warning + details | Soft — can approve |
| Both | Red warning + details | Soft — can approve |
| Warning viewed | Audit log created | Compliance trail |

---

## Project Rules to Follow (from AGENTS.md / CLAUDE.md)

- `AppUser` everywhere — never `User`
- All validation through Form Requests — never inline `$request->validate()`
- Eloquent aggregates only — no `DB::raw()` for counts/sums (use `->count()`, `->sum()`; note Task 2's staff summary uses groupBy + sum — keep Eloquent-friendly; avoid raw selectRaw if possible, iterate collection instead)
- Check sibling files before creating new ones (e.g. `SchemaCapabilities` namespace, existing audit services)
- Run `vendor/bin/pint --dirty` before finishing
- Every change needs a Pest test
- Workflow transitions → create a `LoanRequestChange` audit entry
- Notifications → `LoanRequestNotificationService::EVENT_*` constants
- Follow existing component conventions in `resources/js/components/ui/` and existing pages

## Verification Checklist

- [ ] `vendor/bin/pint --dirty` passes
- [ ] Pest unit tests pass (service — 17 cases)
- [ ] Pest feature tests pass (member auto-fill — 8, staff visibility — 6, dashboard — 4)
- [ ] `npm run lint` / typecheck passes for TS changes
- [ ] Manual: fresh form auto-fills; draft not overridden; member can edit values
- [ ] Manual: staff sees warning + details; can approve despite warning; audit log written
- [ ] Manual: member dashboard shows loan statuses
- [ ] Schema guards verified (wlnmaster missing / columns missing)
