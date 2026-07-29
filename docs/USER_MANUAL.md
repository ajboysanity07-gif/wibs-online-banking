# WIBS Online Banking App Manual

## Overview
This manual describes the key workflows and navigation for each role in the WIBS Online Banking application:

- **Superadmin**
- **Loan Processor**
- **Loan Manager**
- **Member**

Each role section includes the main pages, typical actions, and quick-start checklist.

---

## Superadmin

### Workspace and navigation
Superadmins work in the **Staff** workspace. Their main entry point is the **Staff management** page.

The sidebar includes:

- Staff management
- Audit log (if enabled)
- Organization settings (if active)

### Primary responsibilities

- Create staff accounts
- Promote registered members to staff roles
- Assign and remove workflow roles
- Suspend or reactivate staff access
- Review staff audit history

### Main pages and actions

#### Staff management

Use this page to manage all staff and hybrid staff/member accounts.

- **Promote existing member**
  - Search by name, email, or account number
  - Select a registered member
  - Assign a staff role while preserving member portal access
  - Add a reason for the promotion (recorded in the audit trail)

- **Create staff account**
  - Create a dedicated staff-only login
  - Assign workflow roles without granting member portal access

- **Staff directory**
  - View current roles and staff access status
  - Inspect last recorded staff change notes
  - Suspend or reactivate access as needed

#### Audit log

If available, use the audit log to review system-wide:

- role changes
- staff access changes
- workflow actions
- document access events

### Quick start

- Open **Staff management**
- Use **Promote existing member** when an existing registered user needs a staff role
- Use **Create staff account** for a staff-only access account
- Review the current staff list before assigning new roles
- Check the audit history for recent changes and approvals

### Notes

- The system separates **member access** from **staff access**. Promoting a member does not remove their existing member portal access.
- The `member` role must exist in the database, and registered members must have the role attached in `user_roles` for promotion search to work.

---

## Loan Processor

### Workspace and navigation
Loan Processors use the **Staff** workspace and typically access:

- **My Dashboard**
- **Loan Workflow**

### Primary responsibilities

- Review assigned loan requests
- Start and manage loan processing
- Request member revisions or additional information
- Reject unsuitable applications
- Recommend approval to Loan Manager
- Generate required documents for requests in process

### Main pages and actions

#### My Dashboard

- See assigned queue count
- Track this month’s approved and rejected totals
- Monitor aging applications
- Open requests directly from your queue

#### Loan Workflow

- View loan requests in workflow stages
- Filter by status and search requests
- Open individual requests for detail

#### Request detail actions

Loan Processor actions appear on a request when:

- the request has been assigned to the processor
- the request is in a valid workflow status
- the processor has the required permissions

Typical actions include:

- **Claim** a new request
- **Start review** to begin processing
- **Request revision** when member data is incomplete or incorrect
- **Reject** if the application does not meet requirements
- **Recommend approval** when the loan is ready for manager review
- **Generate documents** during processing
- **Request member action** for additional member confirmation
- **Return to queue** when the request must be re-assigned

### Quick start

- Open **Loan Workflow**
- Filter by the relevant status
- Click a request reference to open details
- Process the request or request member revision
- Recommend approval only after full review

### Notes

- Legacy requests may use older status labels such as `Legacy Submitted` or `Legacy Pending Co-Maker Signatures`.
- The audit trail on the request page tracks workflow history and member action requests.

---

## Loan Manager

### Workspace and navigation
Loan Managers use the **Staff** workspace and the **Loan Workflow** page.

### Primary responsibilities

- Review processor recommendations
- Approve or decline loan requests
- Return requests for additional processing when needed

### Main pages and actions

#### Loan Workflow

- Locate requests in **For Loan Manager Review**
- Open request details to review loan data and history

#### Approval actions

Loan Manager actions appear when the request is in the recommended stage and the manager has permission.

- **Approve** a recommended request
- **Decline** a recommended request
- **Return for processing** if the request needs more work

### Quick start

- Open **Loan Workflow**
- Find requests with status `For Loan Manager Review`
- Open the request detail page
- Choose **Approve** or **Decline** based on review

### Notes

- Managers only see approval actions on requests ready for decision.
- If the request is not in the expected status, the approval buttons are hidden.

---

## Member

### Workspace and navigation
Members use the **Member** workspace and can access:

- **Overview**
- **Loans**
- **Loan Security**
- **Loan requests**
- **Settings**

### Primary responsibilities

- Submit and track loan requests
- View loan status and summaries
- Manage account profile and contact details

### Main pages and actions

#### Overview

- View profile summary, account number, and status
- See recent account activity
- Review loan and savings summaries

#### Loan Requests

- Track loan drafts and requests in progress
- Filter requests by status
- Open requests to see details and next steps
- Submit new loan requests using `Request loan`

#### Loans

- Review active loan records
- Access loan details and payment summaries

#### Loan Security

- View security or collateral information if available

#### Settings

- Update profile details
- Review account status
- Link membership if needed

### Quick start

- Open **Overview** to confirm account status
- Open **Loan Requests** to see active and past applications
- Use **Request loan** to start a new application
- Open **Loans** to review current loan records
- Use **Settings** to update profile and contact info

### Notes

- Member access requires a valid account number and member role.
- If a user has both member and staff access, the member portal remains separate from staff workflows.

---

## Common interface guidance

### Loan request statuses
The application uses workflow status labels such as:

- Draft
- Pending Processing
- In Processing
- Awaiting Member Correction
- Awaiting Member Information
- For Loan Manager Review
- Approved - For WIBS Processing
- Declined by Loan Manager
- Rejected During Processing
- Converted to Loan
- Cancelled

Legacy requests may show older labels such as `Legacy Submitted`.

### Audit trail

- The audit trail shows workflow events and staff/member actions.
- For staff pages, the audit trail may appear in the main detail area or sidebar depending on the route.

### Document access

- Approved documents appear on request details after approval
- Document download buttons are shown when documents are available

---

## Appendix: support notes

### Fixing missing member role data
If old registered users do not appear in the promotion search, the issue is usually missing `member` role entries in `user_roles`.

Recommended commands for deployed environments:

```bash
php artisan loan-workflow:seed-permissions
php artisan members:backfill-roles
```

### Role definitions

- `Superadmin` — full staff management and audit access
- `Loan Processor` — loan review, revision, rejection, recommend approval
- `Loan Manager` — approve or decline processor recommendations
- `Member` — borrower portal access and loan request submission
