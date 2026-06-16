# Loan Workflow Production Deployment

This guide covers Phase 7.1 production deployment for the RBAC loan workflow, document generation, notifications, private storage, deterministic repair, and staged release checks.

## Commands

- `php artisan loan-workflow:preflight --stage=pre-migration`
  - Read-only pre-migration readiness check.
  - Reports pending repository migrations as review items instead of deployment blockers.
- `php artisan loan-workflow:preflight --stage=post-migration`
  - Strict final deployment gate.
  - This is also the default behavior of `php artisan loan-workflow:preflight`.
- `php artisan loan-workflow:seed-permissions`
  - Seeds only the loan workflow roles, permissions, role-permission mappings, and legacy admin/superadmin/member user-role backfills.
  - Preserves existing staff assignments, custom unrelated roles, and custom unrelated permissions.
- `php artisan loan-workflow:seed-permissions --dry-run`
  - Reports workflow RBAC changes without committing them.
- `php artisan loan-workflow:repair`
  - Dry-run by default.
  - Reports deterministic loan workflow repairs without modifying data.
- `php artisan loan-workflow:repair --apply`
  - Applies approved deterministic repairs.
- `php artisan loan-workflow:smoke-test`
  - Read-only dependency and configuration check.
- `php artisan loan-workflow:deployment-check --stage=pre-migration`
  - Safe helper that runs staged preflight and smoke checks without applying migrations, seeding, or repairs.
- `php artisan loan-workflow:send-reminders`
  - Queues due member reminders once per request and event, subject to the configured reminder cap.
- `php artisan loan-workflow:cleanup-temp-files --dry-run`
  - Shows which temporary generation files would be removed.
- `php artisan loan-workflow:cleanup-temp-files`
  - Deletes only expired files under the configured temporary workflow directory.

## Preflight Modes

### Pre-migration mode

`loan-workflow:preflight --stage=pre-migration` is read-only and safe to run before `migrate --force`.

It verifies:

- database connectivity
- pending migrations
- that pending migrations are known repository migrations
- generated-document storage backup scope and configuration safety
- legacy workflow data only when the required legacy tables and columns exist
- queue, failed-job, scheduler, mail, and SMS configuration

It treats these as deferred instead of failed when the new schema is not available yet:

- `staff_access_controls`
- `user_role_changes`
- `loan_request_data_changes`
- `loan_request_data_entries`
- `loan_request_documents`
- `loan_request_notification_events`
- `loan_workflow_repairs`
- `loan_requests.workflow_version`

It still fails for genuine blockers such as:

- inaccessible database
- missing or unsafe generated-document storage configuration
- unknown migration history
- repository drift in the migrations table
- unknown loan-request statuses
- invalid `user_roles` rows that point to missing users or roles
- partial schema states that are unsafe to continue with

Expected pending repository migrations are warnings in pre-migration mode, not blockers.

### Post-migration mode

`loan-workflow:preflight --stage=post-migration` is the strict final gate.

It fails when any of the following remain true:

- migrations are still pending
- workflow roles, permissions, or role mappings are missing
- required Phase 5 to 7 tables or columns are missing
- workflow versions are missing
- active legacy `pending_co_maker_signatures` rows remain
- inactive or ineligible staff assignments remain
- generated files are missing
- document checklist rows are missing
- current generated documents have mismatched source hashes
- duplicate notification events exist
- v2 recommended, awaiting-member-acceptance, approved, or converted requests violate document gates
- other blocking production-support checks fail

Do not continue to public traffic unless strict post-migration preflight passes.

## Deterministic Repair

`loan-workflow:repair` remains dry-run by default.

`loan-workflow:repair --apply` can safely perform only deterministic fixes:

- backfill missing workflow versions
- normalize active legacy `pending_co_maker_signatures` rows to `pending_review`
- relocate generated files from the legacy storage root into private storage when the file still exists
- mark missing generated files as `generation_failed`
- backfill missing generated checksums
- refresh missing or stale document checklist rows
- release assignments held by inactive or ineligible staff using the existing assignment rules

Repair constraints:

- every applied repair is written to `loan_workflow_repairs`
- legacy co-maker status normalization is audited through repair, not manual SQL
- repairs do not send email or SMS
- repairs do not alter already terminal requests
- repairs are idempotent

The repair command never fabricates missing document content, financial values, template placements, or official records.

## Workflow Permission Seeding

Use `php artisan loan-workflow:seed-permissions` after migrations instead of `php artisan db:seed`.

The dedicated command is:

- idempotent
- limited to workflow roles, permissions, role mappings, and legacy admin/superadmin/member backfills
- safe for existing staff assignments because it only attaches missing user-role rows
- safe for custom unrelated roles and permissions because it does not delete or rewrite them
- dry-runnable with `--dry-run`
- conflict-aware and non-zero on unresolved workflow-role conflicts

## Deployment Sequence

Use this production-safe order:

1. Back up the database.
2. Back up the private generated-document storage.
3. Deploy or check out the exact target commit.
4. Install PHP and Node dependencies and build frontend assets.
5. Enable maintenance mode.
6. Stop or pause queue workers.
7. Run pre-migration preflight.
8. Review pending migrations.
9. Run `php artisan migrate --force`.
10. Run `php artisan loan-workflow:seed-permissions`.
11. Run `php artisan loan-workflow:repair` in dry-run mode.
12. Review all proposed repairs.
13. Run `php artisan loan-workflow:repair --apply` only when approved.
14. Run strict post-migration preflight.
15. Run `php artisan loan-workflow:smoke-test`.
16. Rebuild caches.
17. Restart queue workers and scheduler.
18. Disable maintenance mode.
19. Run functional smoke tests.
20. Monitor queues, notifications, document storage, and logs.

Do not continue to public traffic unless step 14 and step 15 pass.

### Recommended command flow

```bash
php artisan down
php artisan loan-workflow:preflight --stage=pre-migration
php artisan migrate --force
php artisan loan-workflow:seed-permissions
php artisan loan-workflow:repair
php artisan loan-workflow:repair --apply
php artisan loan-workflow:preflight --stage=post-migration
php artisan loan-workflow:smoke-test
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
```

## Safe Deployment Helper

`php artisan loan-workflow:deployment-check --stage=pre-migration` is safe to run before the actual release window.

It may orchestrate:

- staged preflight
- smoke-test style dependency checks

It does not:

- apply migrations
- seed permissions
- apply repairs
- send notifications
- modify production data

## Required Services

The deployment needs all of the following running together:

- the Laravel application
- one scheduler process
- one or more queue workers
- the configured queue backend
- failed jobs storage
- private filesystem storage for generated documents

Recommended scheduler command:

```cron
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

The scheduler must run:

- `loan-workflow:send-reminders` at `09:00`
- `loan-workflow:cleanup-temp-files` hourly

Recommended queue worker command:

```bash
php artisan queue:work <connection> \
  --queue=loan-workflow,loan-workflow-notifications \
  --tries=5 \
  --timeout=120 \
  --backoff=60,300,900 \
  --sleep=3 \
  --max-time=3600
```

Current repository support:

- the checked-in `docker-compose.yml` now includes `app`, `queue`, and `scheduler` services
- the Docker queue worker is pinned to `loan-workflow,loan-workflow-notifications`
- no Supervisor configuration is checked in, so Supervisor-based environments must provide equivalent worker and scheduler programs out of band

Runtime note:

- preflight and smoke verify configuration separately from runtime
- they can confirm queue names, `after_commit`, failed-job storage, and scheduler registration
- they cannot prove that an external Supervisor or Docker process is alive unless a separate heartbeat or process inspection is available

## Storage Rules

Generated loan workflow documents are stored on the private disk configured by:

- `LOAN_WORKFLOW_DOCUMENT_DISK`
- `LOAN_WORKFLOW_DOCUMENT_DIRECTORY`
- `LOAN_WORKFLOW_TEMP_DIRECTORY`
- `LOAN_WORKFLOW_TEMP_RETENTION_HOURS`

Production expectations:

- generated documents stay on private storage
- every download goes through application authorization
- stored paths are validated against allowed roots
- download names are sanitized
- final and historical generated versions are preserved
- file size and SHA-256 checksum are recorded for generated artifacts
- only temporary generation directories are cleaned automatically
- final or historical loan documents are never auto-deleted by the cleanup task

## Staging Rehearsal

Run a full rehearsal before production:

1. Restore a recent sanitized production database backup into disposable staging data.
2. Restore a copy of private generated-document storage.
3. Deploy the exact production candidate commit.
4. Disable or redirect real email and SMS providers before any queue or reminder test.
5. Run `php artisan loan-workflow:deployment-check --stage=pre-migration`.
6. Run `php artisan migrate --force`.
7. Run `php artisan loan-workflow:seed-permissions`.
8. Run `php artisan loan-workflow:repair`.
9. Review the dry-run repairs.
10. Run `php artisan loan-workflow:repair --apply` only after review.
11. Run `php artisan loan-workflow:preflight --stage=post-migration`.
12. Run `php artisan loan-workflow:smoke-test`.
13. Verify the two known active legacy `pending_co_maker_signatures` records are normalized through audited repair entries in `loan_workflow_repairs`.
14. Test one legacy approved request.
15. Test one legacy active request.
16. Test one complete v2 request.
17. Verify private generated-document access through the application route, not by direct public storage access.
18. Exercise queues and reminders without delivering unintended real messages.
19. Rehearse rollback using only disposable staging data.

### Staging delivery safety

For staging and rehearsal environments:

- set `MAIL_MAILER=array` or `MAIL_MAILER=log`, or point SMTP at a local mail sink such as Mailpit
- leave `SEMAPHORE_API_KEY` blank to disable live SMS sending, or point `SEMAPHORE_BASE_URL` at a non-live `.test` endpoint
- do not store production provider credentials in the repository or in checked-in documentation

The smoke test treats non-production mail or SMS configurations as unsafe when they appear to target live providers.

## Recommended Validation Before Public Traffic

Run these from the deployed release:

```bash
php artisan migrate:status --no-interaction
php artisan loan-workflow:preflight --stage=pre-migration
php artisan loan-workflow:preflight --stage=post-migration
php artisan loan-workflow:smoke-test
php artisan route:list
```

`loan-workflow:smoke-test` validates:

- database connectivity
- `loan_requests` table presence
- `loan_request_documents` table presence
- `loan_request_notification_events` table presence
- private storage write, read, and delete round-trip
- asynchronous queue configuration
- `after_commit` enabled on the active queue connection
- workflow queue name configured
- workflow notification queues configured
- failed jobs storage configured
- scheduler commands registered for reminders and temporary cleanup
- runtime worker and scheduler verification reported separately as deferred when no heartbeat exists
- environment-appropriate mail configuration
- environment-appropriate SMS configuration
- document templates present
- workflow action route registered
- workflow permissions seeded

The smoke test does not send a real email or SMS. In non-production it is expected to pass only when delivery is disabled or redirected safely.

## Rollback

Rollback is safe only when the previous release can still understand both the schema and the repaired data state.

Safe rollback cases:

- code-only rollback before migrations were applied
- code-only rollback after deploy verification but before public traffic and before any irreversible repairs were applied

Restore-first rollback cases:

- schema changes were applied and the old release is not compatible with them
- workflow-version backfills or legacy-status repairs were applied
- generated files were moved or rewritten
- the permission model now depends on roles or mappings not present in the old release

Restore-first rollback procedure:

1. Enable maintenance mode.
2. Stop workers and scheduler.
3. Restore the pre-deploy database backup.
4. Restore the pre-deploy private generated-document storage backup.
5. Check out the previous release.
6. Rebuild caches for that release.
7. Restart workers and scheduler only after code, schema, and storage are aligned.
8. Disable maintenance mode.

Do not run the old code against the new schema while workers are still active.

## Remaining Official-Template Blockers

The following checked-in template gaps remain intentionally blocked until an updated official template or verified slot map is provided:

- Authorization recipient and payout placement in `authorization.pdf`
- Barangay official name, title, and locality extras in `undertaking-barangay-officials.pdf`
- Affidavit notarial and witness extras in `affidavit-undertaking.pdf`
- Loan Security Agreement narrative placement for `loan_security_details`

These fields are stored and audited where appropriate, but they are not rendered into generated output without a verified official location.
