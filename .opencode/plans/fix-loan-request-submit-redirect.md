# Fix: Member Submit to Review Redirects to Loan Disbursement Step

## Problem
When a member submits a loan request for review from the review step, they get redirected back to the loan disbursement (banking) step instead of the request being submitted successfully.

## Specific Scenario
- User selects **Check** for release method
- User selects **Cash** for payment method
- User never opens the account picker sheets (since not needed for Check/Cash)
- On submit, redirected back to banking step

## Root Cause
**Server-side validation in `LoanRequestStoreRequest.php` line 531:**
```php
'banking' => ['required', 'array:release_method,release_saved_account_id,payment_option,payment_saved_account_id'],
```

Laravel's `array:keys` rule requires **ALL 4 keys to be present** in the submitted array. When user selects Check/Cash:
- `release_saved_account_id` and `payment_saved_account_id` are never set (remain null)
- Inertia's `useForm` may omit null values from the HTTP request
- Server receives only `{release_method: "Check", payment_option: "Cash"}`
- Array validation fails because 2 keys are missing
- Returns error on `banking` field
- Frontend `resolveStepFromErrors()` sees `banking.*` error → redirects to banking step

## Solution

### Primary Fix: Server-side (LoanRequestStoreRequest.php)
Change the banking array validation to not require optional keys:
```php
// Line 531 - CHANGE FROM:
'banking' => ['required', 'array:release_method,release_saved_account_id,payment_option,payment_saved_account_id'],
// TO:
'banking' => ['required', 'array'],
```

The individual field rules already handle conditional requirements:
- `banking.release_method` - required
- `banking.payment_option` - required  
- `banking.release_saved_account_id` - required_if ATM/Bank Transfer
- `banking.payment_saved_account_id` - required_if ATM Deduction

### Secondary Fix: Client-side (loan-request.tsx) - Defense in depth
Add memoized validation helpers and update `disablePrimary` to prevent submission when any step is incomplete (including review step validation):

```typescript
// Add after skippedStepIds (around line 519)
const isBankingComplete = useMemo(() => {
    const banking = form.data.banking;
    const releaseMethod = banking.release_method;
    const paymentOption = banking.payment_option;

    if (!releaseMethod || !paymentOption) {
        return false;
    }

    if (
        (releaseMethod === 'ATM' || releaseMethod === 'Bank Transfer') &&
        !banking.release_saved_account_id
    ) {
        return false;
    }

    if (
        paymentOption === 'ATM Deduction' &&
        !banking.payment_saved_account_id
    ) {
        return false;
    }

    return true;
}, [form.data.banking]);

const isInsuranceComplete = useMemo(() => {
    if (!insurancePrefilledFromProfile) return true;
    return beneficiariesConfirmed;
}, [insurancePrefilledFromProfile, beneficiariesConfirmed]);

const isDependentsComplete = useMemo(() => {
    if (!dependentsPrefilledFromProfile) return true;
    return dependentsConfirmed;
}, [dependentsPrefilledFromProfile, dependentsConfirmed]);

const isDeclarationsComplete = useMemo(() => {
    const declarations = form.data.declarations;
    return declarations.declaration_truth_confirmation === true &&
           declarations.declaration_data_privacy_consent === true;
}, [form.data.declarations]);
```

Update `disablePrimary` (around line 812):
```typescript
disablePrimary={
    !hasLoanTypes ||
    (currentStep === STEP_INDEX['insurance'] &&
        insurancePrefilledFromProfile &&
        !beneficiariesConfirmed) ||
    (currentStep === STEP_INDEX['banking'] &&
        bankingPrefilledFromProfile &&
        (form.data.banking.release_method === 'Bank Transfer' ||
            form.data.banking.release_method === 'ATM') &&
        !bankAccountConfirmed) ||
    (currentStep === STEP_INDEX['dependents'] &&
        dependentsPrefilledFromProfile &&
        !dependentsConfirmed) ||
    (currentStep === STEP_INDEX['declarations'] &&
        (form.data.declarations.declaration_truth_confirmation !== true ||
            form.data.declarations.declaration_data_privacy_consent !== true)) ||
    // Review step: validate ALL steps
    (isLastStep && (
        !isBankingComplete ||
        !isInsuranceComplete ||
        !isDependentsComplete ||
        !isDeclarationsComplete
    ))
}
```

## Files to Modify
1. `app/Http/Requests/Client/LoanRequestStoreRequest.php` - Line 531 (primary fix)
2. `resources/js/pages/client/loan-request.tsx` - Add validation helpers and update disablePrimary (secondary fix)

## Testing
1. Create new loan request
2. Fill all steps, select Check for release, Cash for payment
3. Go to review step
4. Click Submit
5. Should succeed (not redirect to banking step)
6. Verify loan request appears in "Loan Requests" list with status "Submitted"/"Pending Review"

## Edge Cases Covered
- Emergency loans / 1-month Due date: insurance/health skipped → insurance validation passes
- ATM/Bank Transfer: requires release_saved_account_id
- ATM Deduction: requires payment_saved_account_id
- Pre-filled insurance/dependents: requires confirmation checkboxes
- Declarations: requires both consent checkboxes