# Loan Workflow Production Deployment

This guide covers Phase 7 production acceptance for the RBAC loan workflow, document generation, notifications, private storage, and operational safety checks.

## Commands

- `php artisan loan-workflow:preflight`
  - Read-only deployment gate. Returns a non-zero exit code when blocking issues are found.
- `php artisan loan-workflow:repair`
  - Dry-run by default.
  - Use `--apply` to execute deterministic repairs.
  - Use `--chunk=200` to control scan size.
  - Use `--actor-user-id=<staff-user-id>` when assignment-release repairs must be attributed to a specific staff user.
- `php artisan loan-workflow:smoke-test`
  - Read-only dependency check for database, storage, queue, mail, SMS configuration presence, templates, routes, and seeded workflow permissions.
- `php artisan loan-workflow:send-reminders`
  - Queues due member reminders once per request/event, subject to the configured maximum reminder count.
- `php artisan loan-workflow:cleanup-temp-files --dry-run`
  - Shows which temporary generation files would be removed.
- `php artisan loan-workflow:cleanup-temp-files`
  - Deletes only expired files under the configured temporary workflow directory.

## What Preflight Checks

`loan-workflow:preflight` reports:

- pending migrations
- requests with unknown statuses
- requests missing workflow versions
- invalid role assignments
- active legacy `pending_co_maker_signatures` rows
- requests assigned to inactive or ineligible staff
- missing generated document files
- missing checklist rows
- documents marked current with mismatched source hashes
- duplicate notification events
- v2 recommended, awaiting-member-acceptance, approved, or converted requests with incomplete documents
- queue configuration warnings
- mail and SMS configuration warnings

Blocking issues stop deployment. Warnings do not stop the command, but they should be reviewed before go-live.

## Deterministic Repairs

`loan-workflow:repair --apply` can safely perform only deterministic fixes:

- backfill missing workflow versions
- normalize active legacy co-maker signature statuses
- relocate generated files from the legacy storage root into private storage when the file is still present
- mark missing generated files as `generation_failed`
- backfill missing generated checksums
- refresh missing or stale document checklist rows
- release assignments held by inactive or ineligible staff using the existing assignment rules

Every applied repair is written to `loan_workflow_repairs`. The repair command never fabricates missing document content, financial values, template placements, or official records.

## Required Services

The production stack needs all of the following running together:

- the Laravel application
- one scheduler process
- one or more queue workers
- the configured queue backend
- private filesystem storage for generated documents

Recommended scheduler entry:

```cron
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

The scheduler is responsible for:

- `loan-workflow:send-reminders` at `09:00`
- `loan-workflow:cleanup-temp-files` hourly

Recommended queue worker shape:

```bash
php artisan queue:work <connection> \
  --queue=loan-workflow,loan-workflow-notifications \
  --tries=5 \
  --timeout=120 \
  --backoff=60,300,900 \
  --sleep=3 \
  --max-time=3600
```

Recommended Supervisor programs:

- `wibs-app-queue`
- `wibs-app-scheduler`

Recommended Docker service split:

- `app`
- `queue-worker`
- `scheduler`

During deploys, restart workers with `php artisan queue:restart` after the new code and migrations are in place.

## Storage Rules

Generated loan workflow documents are stored on the private disk configured by:

- `LOAN_WORKFLOW_DOCUMENT_DISK`
- `LOAN_WORKFLOW_DOCUMENT_DIRECTORY`
- `LOAN_WORKFLOW_TEMP_DIRECTORY`
- `LOAN_WORKFLOW_TEMP_RETENTION_HOURS`

Current production expectations:

- generated documents stay on private storage
- every download goes through application authorization
- stored paths are validated against allowed roots
- download names are sanitized
- final and historical generated versions are preserved
- file size and SHA-256 checksum are recorded for generated artifacts
- only temporary generation directories are cleaned automatically
- final or historical loan documents are never auto-deleted by the cleanup task

## Deployment Steps

1. Back up the database.
2. Back up the private generated-document storage directory.
3. Pull the exact target commit for deployment.
4. Enable maintenance mode.
5. Run `php artisan loan-workflow:preflight`.
6. Install PHP and Node dependencies for the target commit.
7. Build frontend assets with `npm run build`.
8. Run database migrations.
9. Rebuild caches.
   - Recommended sequence: `php artisan optimize:clear`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`
10. Restart queue workers with `php artisan queue:restart`.
11. Disable maintenance mode.
12. Run smoke checks.
   - Minimum: `php artisan loan-workflow:smoke-test`
13. Verify notifications and document storage.
   - Confirm new notification events record `queued`, `sent`, or `failed` states without exposing provider secrets.
   - Confirm a generated document can be downloaded through the authorized route from private storage.

Do not continue past step 5 when preflight reports blocking issues.

## Recommended Validation During Deploy

Run these from the deployed release before opening traffic:

```bash
php artisan migrate:status --no-interaction
php artisan loan-workflow:preflight
php artisan loan-workflow:smoke-test
php artisan route:list
```

For the queue path, confirm:

- the active queue connection is not `sync`
- `after_commit` is enabled for the active queue connection
- failed jobs are retained
- the notification queues are being consumed

## Rollback

Rollback is safe only when the new migrations have not changed persisted data in a way the old code cannot understand. If Phase 7 migrations ran and any production traffic hit the new schema, prefer a full database restore over a partial code-only rollback.

Before rollback:

1. Enable maintenance mode.
2. Stop or restart queue workers so old workers do not keep running mixed code.
3. Drain or isolate in-flight queue jobs if possible.

Safe rollback cases:

- code-only rollback before migrations were applied
- code-only rollback after deploy verification but before public traffic and before irreversible data changes

Rollback cases that require restore:

- schema changes were applied and the old release is not compatible with them
- deterministic repairs were applied and the previous release depends on the unrepaired state
- generated files were moved or rewritten and the prior release expects the older layout

Rollback procedure when restore is required:

1. Stop workers.
2. Restore the database backup taken before deployment.
3. Restore the private generated-document storage backup taken before deployment.
4. Check out the prior release.
5. Rebuild caches for that release.
6. Restart workers only after the old code and restored schema are aligned.
7. Disable maintenance mode.

Do not run the old code against the new schema while workers are still active.

## Smoke Test Expectations

`loan-workflow:smoke-test` must pass these checks:

- database connectivity
- `loan_requests` table present
- `loan_request_documents` table present
- `loan_request_notification_events` table present
- private storage write, read, and delete round-trip
- queue configuration present
- mail configuration present
- SMS configuration presence
- document templates present
- workflow action route registered
- workflow permissions seeded

The smoke test does not send a real email or SMS.

## Notification and Queue Notes

Phase 7 hardens notification delivery around these constraints:

- only configured critical events are queued for email or SMS
- event rows are de-duplicated before send
- queued notifications dispatch after commit
- retries update attempt and retry counters
- provider failures are sanitized before staff display
- reminder sends are capped by `LOAN_WORKFLOW_NOTIFICATION_MAX_REMINDERS`
- member action links are temporary signed routes
- member ownership is still checked after login

Staff can review notification history on the staff loan-request detail page, including:

- channel
- event
- queued, sent, or failed status
- timestamps
- retry count
- sanitized provider error

## Remaining Official-Template Blockers

The following checked-in template gaps remain intentionally blocked until an updated official template or verified slot map is provided:

- Authorization recipient and payout placement in `authorization.pdf`
- Barangay official name, title, and locality extras in `undertaking-barangay-officials.pdf`
- Affidavit notarial and witness extras in `affidavit-undertaking.pdf`
- Loan Security Agreement narrative placement for `loan_security_details`

These fields are stored and audited where appropriate, but they are not rendered into generated output without a verified official location.
