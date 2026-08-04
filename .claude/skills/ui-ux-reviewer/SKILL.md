---
name: ui-ux-reviewer
description: Review UX before implementing UI changes — usability, information hierarchy, workflow efficiency, minimalist design. Use before building or altering any page/component in resources/js, especially loan-request workflow screens.
---

# UI/UX Reviewer

Run this review **before** writing UI code, and again on the result before calling the task done.

## Checklist

1. **Workflow fit** — Does this match the actual `LoanWorkflowStatus` step the user is in? Don't show actions/fields irrelevant to the current status (see CLAUDE.md Workflow Status Flow).
2. **Information hierarchy** — Most important info/action is visually dominant (position, size, weight). Secondary/rarely-used controls are de-emphasized (menus, "more" disclosure) not competing for attention.
3. **Progressive disclosure** — Don't show every field/detail by default. Collapse audit trails, raw payloads, rarely-needed metadata behind expanders (see `loan-request-audit-trail.tsx` for the existing pattern).
4. **Minimalism** — Remove UI that doesn't serve the current task. No decorative elements, no duplicate affordances for the same action.
5. **Consistency** — Spacing, typography, and component choices match sibling pages in `resources/js/pages/{admin,client,staff,superadmin}/` and `resources/js/components/`.
6. **Feedback & state** — Loading, empty, error, and success states are all handled and visually distinct (use existing `skeleton.tsx`, `spinner.tsx`, `sonner.tsx` toast patterns).
7. **Accessibility** — Keyboard operable, labeled form controls (`label.tsx` + `field-message.tsx`), sufficient contrast, focus visible.

## Output

State findings as a short list (what's good, what to change) before touching code. If the change is trivial (copy tweak, single prop), skip the formal review and just note the one consideration that matters.
