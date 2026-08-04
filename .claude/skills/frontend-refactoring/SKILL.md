---
name: frontend-refactoring
description: Keep resources/js components clean and maintainable — reduce duplication, extract reusable components/hooks when appropriate. Use when a component grows large, logic repeats across files, or after adding UI features.
---

# Frontend Refactoring

Apply when touching or reviewing files in `resources/js/`.

## Signals to extract

- The same JSX block (card layout, status badge, form group) appears in 2+ places → extract a component into `resources/js/components/`, colocated with siblings (e.g. `loan-request/` for loan-request-specific UI).
- A component mixes data-fetching/derivation logic with rendering and exceeds ~150-200 lines → split into a hook (`resources/js/hooks/`) and a presentational component.
- Prop-drilling more than 2 levels for the same value → consider context or lifting state, but only if it's genuinely shared, not as a default.
- Repeated type shapes across components → consolidate into `resources/js/types/*.ts` rather than redefining inline.

## Rules

- Don't extract prematurely — two similar blocks used once each is fine; three+ real occurrences justifies a shared component (matches the project's general no-premature-abstraction rule).
- New shared components should reuse shadcn/ui primitives (see `shadcn-ui-expert` skill) — don't duplicate styling logic.
- Preserve existing prop naming/conventions of sibling components in the same folder before inventing new ones.
- After extracting, verify no behavior changed — run type-check (`types` skill / `tsc --noEmit`) and relevant `tests/js` tests.
- Don't refactor unrelated code while fixing a bug or adding a feature — keep refactors scoped and, if large, called out separately.
