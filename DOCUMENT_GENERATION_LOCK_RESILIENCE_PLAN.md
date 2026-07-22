# Plan — Document Generation Lock Resilience (Crash Recovery + Per-Document Granularity)

> **Status:** Plan only. No implementation code has been written.
> **Scope:** `LoanRequestDocumentWorkflowService::withDocumentGenerationLock()` and its two callers
> (`generateAll()`, `generateDocument()`) only. This is locking/concurrency infrastructure shared by
> **all ten** document types (`app/LoanRequestDocumentKey.php`) — it does not touch any document-specific
> rendering, field-map, or data logic (AU, GREPALIFE, or otherwise). Confirmed by direct read of every
> touched file this session; no document-specific code appears anywhere in the diff this plan describes.

---

## 0. What's wrong, in one paragraph

`withDocumentGenerationLock()` (`app/Services/LoanRequests/LoanRequestDocumentWorkflowService.php:1070-1087`)
wraps every document-generation call in a single `Cache::lock('loan-workflow:documents:{id}', 120)`. Two
independent problems live in that one line: (1) if the request dies mid-render from an abnormal termination
(fatal error, OOM kill, `max_execution_time`) rather than an ordinary exception, the `finally` block that
normally releases the lock never runs, and the lock sits orphaned for up to 120s; this was directly
triggered this session by a header-image-scale bug (fixed in `0dca2e9`) that plausibly crashed mid-render.
(2) The lock key covers the *entire loan request*, not a single document, so an unrelated single-document
regenerate (e.g. GREPALIFE) blocks — and gets blocked by — a "Regenerate All" run or a regenerate of a
completely different document, even though the two write to independent files and rows.

---

## 1. Confirmed current state (read directly this session)

### 1.1 The lock today

```php
// app/Services/LoanRequests/LoanRequestDocumentWorkflowService.php:1070-1087
private function withDocumentGenerationLock(
    LoanRequest $loanRequest,
    callable $callback,
): mixed {
    $lock = Cache::lock(
        sprintf('loan-workflow:documents:%d', $loanRequest->id),
        120,
    );
    $result = $lock->get($callback);

    if ($result !== false) {
        return $result;
    }

    throw ValidationException::withMessages([
        'documents' => 'Document generation is already running for this request.',
    ]);
}
```

Called from exactly two places, both in the same file:

- `generateAll()` (`:251-316`) — iterates all applicable documents from `refreshChecklist()` sequentially,
  in a single `foreach`, inside one lock acquisition.
- `generateDocument()` (`:318-334`) — single-document regenerate, calls `generateDocumentInternal()` inside
  one lock acquisition.

Both funnel through `generateDocumentInternal()` (`:336-416`), which is the actual write path: it computes
a new `generated_version`, writes the file via `documentStorage`, and `save()`s the `LoanRequestDocument`
row. **This method is not touched by this plan** — locking wraps it, nothing inside it changes.

### 1.2 Call sites outside this file

- `app/Http/Controllers/Spa/LoanRequestWorkflowController.php:406-434` (`generateDocuments()`) — the only
  HTTP entry point. If the request has a `document_key`, it calls `generateDocument()` once; otherwise it
  calls `generateAll()`. These are mutually exclusive per HTTP request, but nothing stops two different HTTP
  requests (one single-doc, one bulk, or two single-doc on different keys) from arriving concurrently.
- `app/Services/LoanRequests/LoanRequestProcessingService.php:694-703` — inside `approveByManager()`'s DB
  transaction, calls `generateDocument()` **twice in a row**, for `LoanInformation` then `PromissoryNote`.
  Each call acquires and releases its own lock; they don't overlap with each other (sequential, same
  request), but they are real concurrent-lock participants relative to *other* requests touching those two
  documents. Any redesign of the lock key must keep working for this call site without modification — it
  already calls `generateDocument()` with a specific key, so it is naturally compatible with per-document
  locking.

### 1.3 Laravel's lock mechanics (read from vendor source this session — this is the load-bearing finding)

`Lock::get()` (`vendor/laravel/framework/src/Illuminate/Cache/Lock.php:88-101`):

```php
public function get($callback = null)
{
    $result = $this->acquire();
    if ($result && is_callable($callback)) {
        try {
            return $callback();
        } finally {
            $this->release();
        }
    }
    return $result;
}
```

Ordinary `\Throwable`s (including the `ValidationException` thrown inside `generateDocumentInternal()` on a
render failure, `:386-396`) still hit the `finally` and release the lock correctly today. Only genuinely
abnormal termination skips it.

`DatabaseLock::acquire()` (`vendor/laravel/framework/src/Illuminate/Cache/DatabaseLock.php:70-98`):

```php
} catch (QueryException) {
    $updated = $this->connection->table($this->table)
        ->where('key', $this->name)
        ->where(function ($query) {
            return $query->where('owner', $this->owner)->orWhere('expiration', '<=', $this->currentTime());
        })->update([...]);
    $acquired = $updated >= 1;
}
```

**This is already a self-healing mechanism.** An orphaned lock row from a crash is not a permanent
deadlock — the very next acquisition attempt against that key, from anyone, succeeds once
`expiration <= now()`, because the `UPDATE` matches on expiration alongside owner. So Bug 1's actual blast
radius is **bounded by the TTL window**, not unbounded: for up to 120s after a crash, every generation
attempt on that loan request (today: on *any* document, because the key isn't per-document) fails fast with
the "already running" `ValidationException`, then self-heals with no operator intervention. This reframes
Bug 1 from "fix a deadlock" to "shrink an availability window and add defense-in-depth," which changes what
Phase 1 needs to do.

### 1.4 Why register_shutdown_function is viable here, and why it isn't a full fix

`register_shutdown_function()` callbacks run during PHP's shutdown sequence, which **does** fire after PHP
fatal errors that are still handled by the Zend engine — `memory_limit` exhaustion and `max_execution_time`
exceeded both fall in this category (both are plausible here: `generateAll()` loops over up to 10 documents
in one request, and `ApprovedLoanPdfTemplateService` uses `setasign/Fpdi` + `TCPDF` plus
`SignaturePngService` image compositing per `app/Services/LoanRequests/ApprovedLoanPdfTemplateService.php`
— all pure-PHP CPU/memory work, confirmed via `composer.json`; no subprocess or headless-browser renderer
is involved, so there is no child-process-crash surface to worry about). Shutdown functions do **not** run
when the OS OOM-killer sends `SIGKILL`, or on a C-level segfault in an extension — those terminate the
process before any PHP userland code, including shutdown handlers, can execute. That residual gap is real
but already bounded by §1.3's TTL self-heal, so it does not need (and per the plan's own scoping, should
not get) a bespoke solution like an external supervisor process — that would be disproportionate operational
surface for a gap the existing mechanism already caps.

**Conclusion for Phase 1:** ship a `register_shutdown_function`-based proactive release as defense-in-depth
for the catchable subset of crashes (this stack's realistic crash modes), while leaving the uncatchable
subset to the TTL self-heal that already exists. Do not attempt anything beyond that.

---

## Phase 1 — Crash resilience

### 1.A Mechanism decision (per the three options posed)

- **(a) Shrink the TTL** — deferred to Phase 2. With a single shared key, the TTL must accommodate the
  worst case (`generateAll()` over up to 10 documents), so shrinking it now — before the key is split —
  risks exactly the "genuine race" the investigation already flagged: a legitimately slow `generateAll()`
  losing its lock mid-flight to a second request. Tightening TTL safely requires Phase 2's per-document key
  first (tight TTL for the common single-doc case, a separate allowance for the rarer bulk case). Phase 1
  does not change the `120` constant.
- **(b) try/catch/finally around the callback** — confirmed not viable as the *sole* mechanism: PHP fatal
  errors from OOM-kill/segfault bypass `finally` entirely, by design, at the language level. However, per
  §1.4, `register_shutdown_function()` covers the realistic crash modes this pure-PHP rendering stack can
  actually hit. This is the mechanism Phase 1 implements.
- **(c) Lazy stale-lock sweep** — already exists, unconditionally, in `DatabaseLock::acquire()` (§1.3). No
  new code needed for the "does it ever permanently deadlock" question — the answer is already no. This
  finding is why Phase 1 is scoped to *shrinking the blocked window*, not *preventing deadlock* (already
  prevented).

### 1.B Implementation

Wrap the existing lock/callback pair with a shutdown-guarded release, scoped to the exact `Lock` object's
owner so a normal completion never double-releases into someone else's lock:

```php
private function withDocumentGenerationLock(
    LoanRequest $loanRequest,
    callable $callback,
): mixed {
    $lock = Cache::lock(
        sprintf('loan-workflow:documents:%d', $loanRequest->id),
        120,
    );

    $released = false;
    register_shutdown_function(static function () use ($lock, &$released): void {
        if (! $released) {
            $lock->release();
        }
    });

    try {
        $result = $lock->get($callback);
    } finally {
        $released = true;
    }

    if ($result !== false) {
        return $result;
    }

    throw ValidationException::withMessages([
        'documents' => 'Document generation is already running for this request.',
    ]);
}
```

Why this is safe:
- Normal path (success or ordinary exception): `Lock::get()`'s own `finally` already released the lock; the
  outer `finally` then flips `$released = true`; the shutdown function becomes a no-op. No double work on
  the happy path.
- Abnormal termination mid-callback: neither `finally` runs, `$released` stays `false`, the shutdown
  function fires (when the crash type permits it) and calls `$lock->release()`, which is owner-scoped
  (`Lock::isOwnedByCurrentProcess()`) — it only deletes the row if this process still owns it, so it can
  never steal a lock that has already expired and been picked up by someone else.
- `register_shutdown_function` is per-request even under PHP-FPM (each request gets a fresh execution
  context), so there is no cross-request contamination risk from registering it unconditionally on every
  call.

No document-specific code is touched — this is a self-contained change to one private method.

### 1.C Test strategy

Standard sequential Pest tests cannot trigger a real abnormal termination without killing the test runner
itself, so verification splits into automated and manual:

- **Automated (Pest, `tests/Feature/...`):** confirm the *self-heal* behavior end-to-end using the same
  direct `cache_locks` inspection technique the investigation already used:
  1. Manually insert an orphaned row into `cache_locks` for key `loan-workflow:documents:{id}` with
     `expiration` in the past (simulating a crash that left a stale lock).
  2. Call `generateDocument()` (or `generateAll()`) normally.
  3. Assert it succeeds (proves `DatabaseLock::acquire()`'s steal-on-expiry still works after this change)
     and assert the row's `owner` changed to the new request's lock owner.
  - Separately, assert the "still genuinely running" case is unaffected: insert a row with `expiration` in
    the future under a different owner, call the service method, assert it throws the existing
    `ValidationException` with the existing message.
- **Manual/scripted, one-time verification (not a permanent Pest test):** since deliberately crashing the
  PHP process is exotic for a test suite, verify the shutdown-function path with a small standalone script
  (run via `php artisan tinker` or a throwaway `Bash` invocation) that calls the real service method with a
  callback that calls `exit()` or exhausts `memory_limit` mid-callback, then queries `cache_locks` in a
  second step to confirm the row was deleted immediately rather than surviving to the TTL. Document the
  result inline in the commit message rather than leaving it as a lingering script file.
- Full Pest suite (`php artisan test` / `vendor/bin/pest`) at the end of the phase, since this is shared
  infrastructure — not just the AU-related tests currently modified on this branch.

### 1.D Commit + rollback

One commit: the `withDocumentGenerationLock()` change plus the new lock-recovery Pest test(s). Rollback is
a straight revert — the change has no migration, no config, and no callers outside this one method.

---

## Phase 2 — Per-document lock granularity

### 2.A Key design

Split into two lock scopes:

- **Per-document key:** `loan-workflow:documents:{loanRequestId}:{documentKey}` — used by `generateDocument()`
  for a single regenerate, and by `generateAll()` for each document it's about to write.
- **Request-level umbrella key:** `loan-workflow:documents:{loanRequestId}` (the existing key, unchanged) —
  used by `generateAll()` only, to keep "Generate All" itself a single coherent, non-overlapping operation
  (prevents two concurrent "Regenerate All" clicks from interleaving into one confusing combined result).

### 2.B Coordinating `generateAll()` against a concurrent single-document regenerate — the race this phase must not introduce

The prompt's own risk callout is the right one to worry about: if `generateAll()` only took the umbrella
lock and released each per-document lock as it went, a concurrent single-doc regenerate on a document
`generateAll()` had *already finished* could run at the same time as `generateAll()` continuing on to the
next document. That's fine in isolation, but if the concurrent regenerate targets a document `generateAll()`
is *currently* writing, both code paths would call `generateDocumentInternal()` for the same
`LoanRequestDocument` row/file concurrently — a real "two processes writing the same file" race, non-atomic
`generated_version` increment included.

**Decision:** `generateAll()` acquires the per-document lock for each document individually, immediately
before calling `generateDocumentInternal()` for it, and releases it right after — using the exact same
per-document lock key `generateDocument()` uses. Because every write to a given document's row/file, from
either call path, is always guarded by that same key, the two paths can never interleave on the same
document, regardless of which one started first:

```php
public function generateAll(LoanRequest $loanRequest, AppUser $actor): array
{
    return $this->withRequestGenerationLock($loanRequest, function () use ($loanRequest, $actor): array {
        $results = [];
        foreach ($this->refreshChecklist($loanRequest) as $document) {
            $documentKey = LoanRequestDocumentKey::from($document->document_key);
            // ...existing not-applicable / blockers short-circuits, unchanged...

            try {
                $generatedDocument = $this->withDocumentGenerationLock(
                    $loanRequest,
                    $documentKey,
                    fn () => $this->generateDocumentInternal($loanRequest, $documentKey, $actor),
                );
                $results[] = [...]; // unchanged shape
            } catch (ValidationException $lockException) {
                // this specific document is busy elsewhere right now — record and move on,
                // do not abort the rest of the batch
                $results[] = [
                    'key' => $documentKey->value,
                    'status' => $document->readiness_status?->value ?? ...,
                    'message' => 'Document generation is already running for this document.',
                ];
            } catch (\Throwable $exception) {
                // ...existing generation-failure handling, unchanged...
            }
        }
        return $results;
    });
}

public function generateDocument(
    LoanRequest $loanRequest,
    LoanRequestDocumentKey $documentKey,
    AppUser $actor,
): LoanRequestDocument {
    return $this->withDocumentGenerationLock(
        $loanRequest,
        $documentKey,
        fn () => $this->generateDocumentInternal($loanRequest, $documentKey, $actor),
    );
}
```

`withDocumentGenerationLock()` and a new `withRequestGenerationLock()` become thin wrappers around a shared
low-level `withLock(string $key, int $seconds, callable $callback)` that carries forward Phase 1's
shutdown-guarded release unchanged (implemented once, reused by both scopes — not duplicated).

**Why this doesn't deadlock:** `generateAll()` always acquires umbrella-then-per-document, in that fixed
order, and releases the per-document lock before moving to the next iteration (never holds two per-document
locks at once). `generateDocument()` only ever acquires a per-document lock, never the umbrella. Since a
single-doc call never holds a per-document lock while attempting to acquire the umbrella, there is no lock
ordering inversion between the two call paths, so no deadlock is possible between them.

**Why the "busy document, skip and continue" behavior is the right call, not a scope violation:**
`generateAll()` already isolates per-document failures this way — the existing `catch (\Throwable $exception)`
block (`:298-311`) turns a generation failure for one document into a result row rather than aborting the
whole batch. Treating "this document is locked by another operation right now" the same way is consistent
with that existing pattern, and it's a locking/reporting decision, not a change to what gets rendered or
written for any document — the "must not change document-generation behavior" constraint is about
render/field-map logic, which this doesn't touch.

### 2.C TTL values

With the key split, the two scopes can finally get TTLs sized to what they actually guard, instead of one
constant covering the worst case for both:

- **Per-document TTL:** should reflect a single document's realistic generation time (FPDI/TCPDF render +
  `SignaturePngService` compositing) with a safety margin — no measurement of this exists yet in the
  codebase or in this investigation. Before picking a number, measure it: wrap
  `generateDocumentInternal()` with a temporary `Log::info` duration timer (or use `microtime(true)`
  before/after in a throwaway script) across a few representative documents, including the heaviest one
  (whichever composites the most signature images), then set the TTL to roughly 3-5x the observed p99. Do
  not guess a number without measuring — that's exactly the "genuine race" risk from the investigation, just
  relocated to the per-document scope instead of the request scope.
- **Umbrella TTL:** keep it conservative (the existing `120`, or slightly above the measured full-batch time
  across all applicable documents plus margin) — `generateAll()` is the rarer, heavier operation, and there
  is much less benefit to shrinking this one aggressively since it doesn't block unrelated single-document
  regenerates anymore once §2.B lands.

Record the measured numbers and the chosen constants in the Phase 2 commit message so the reasoning isn't
lost.

### 2.D Test strategy

- **Different documents don't block each other:** manually acquire
  `Cache::lock('loan-workflow:documents:{id}:grepalife', ...)` and hold it open (via a lock object kept in
  scope, not released), then call `generateDocument()` for `AffidavitUndertaking` on the same loan request
  and assert it succeeds normally.
- **Same document is still mutually exclusive:** same setup, but call `generateDocument()` for `Grepalife`
  itself and assert the existing `ValidationException` fires.
- **`generateAll()` takes the umbrella lock:** hold
  `Cache::lock('loan-workflow:documents:{id}', ...)` externally, call `generateAll()`, assert it throws.
- **The critical regression test — concurrent `generateAll()` and a same-document single regenerate don't
  interleave:** hold the per-document lock for one specific document key externally (simulating "a single
  regenerate for this document is already running elsewhere"), call `generateAll()`, and assert: (1) it does
  **not** throw for the whole batch, (2) the result array contains a row for that specific document with the
  "already running" message from §2.B, and (3) every *other* document in the result array completed
  normally. This is the concrete, deterministic stand-in for true parallel requests that the prompt asked
  for — it exercises the exact lock-contention path without relying on real concurrency.
- Extend the existing `tests/Feature/LoanRequestGenerateDocumentsBulkTest.php` rather than creating a
  parallel file, since it already has the right factory/role setup for hitting
  `route('spa.workflow.loan-requests.documents.generate', ...)`.
- Full Pest suite again at the end of this phase.

### 2.E Commit + rollback

One commit: the key-split, the `withRequestGenerationLock`/shared `withLock` refactor, the "busy document"
catch branch in `generateAll()`, the chosen TTL constants (with measured reasoning in the commit message),
and the new granularity tests. Rollback is a straight revert of this commit on top of Phase 1's — the two
phases don't share a diff region beyond both touching `withDocumentGenerationLock()`'s signature, so
reverting Phase 2 alone (keeping Phase 1) is clean; reverting both in sequence is also clean since the file's
only external contract (`generateAll()`, `generateDocument()` public signatures) never changes.

---

## 3. Explicit non-goals

- No change to `generateDocumentInternal()`, `documentDataForGeneration()`, any `PdfFieldMap`, or any
  document-specific rendering/data logic. Confirmed above: every change in this plan lives in
  `withDocumentGenerationLock()`, its new sibling `withRequestGenerationLock()`/`withLock()`, and the
  lock-acquisition wrapping inside `generateAll()`'s loop — nothing inside the try block that actually
  builds a document changes.
- No queue/worker changes. `QUEUE_CONNECTION=database` stays unused for this flow, per the investigation;
  moving generation onto a queue is a materially different design (and would change the lock TTL math
  entirely) and is out of scope here.
- No handling for OS-level OOM-kill/segfault beyond what `DatabaseLock::acquire()`'s existing TTL self-heal
  already bounds — see §1.4.
