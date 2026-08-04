---
name: shadcn-ui-expert
description: Prefer existing shadcn/ui components and follow this project's design-system patterns before writing custom UI. Use for any new component, form, or layout in resources/js.
---

# shadcn/ui Expert

This project uses shadcn/ui (`new-york` style, `neutral` base, CSS variables, Lucide icons — see `components.json`).

## Before building anything

1. **Check `resources/js/components/ui/` first.** Existing primitives: `alert`, `avatar`, `badge`, `breadcrumb`, `button`, `card`, `checkbox`, `collapsible`, `command`, `data-table` (+ `data-table-pagination`), `dialog`, `dropdown-menu`, `field-message`, `icon`, `input`, `input-otp`, `label`, `navigation-menu`, `password-input`, `popover`, `select`, `separator`, `sheet`, `sidebar`, `skeleton`, `sonner`, `spinner`, `table` (+ `table-skeleton`), `tabs`, `toggle`, `toggle-group`, `tooltip`.
2. **Check domain components** in `resources/js/components/loan-request/` and neighboring folders before creating a new one — many workflow-specific patterns (audit trail, processing panel) already exist.
3. Only add a new shadcn primitive via the CLI (`npx shadcn@latest add <component>`) if the pattern truly doesn't exist — don't hand-roll a component shadcn already ships.
4. Never build a custom modal/dropdown/tooltip/table from scratch — compose from the primitives above.

## Conventions to follow

- Use the `@/components/ui`, `@/lib`, `@/hooks` aliases exactly as defined in `components.json`.
- Variant styling via `class-variance-authority` (`cva`), matching the pattern already in `button.tsx` — don't invent a parallel variant system.
- Icons from `lucide-react` only, wrapped through `icon.tsx` where that pattern is already used.
- Compose, don't fork: extend a shadcn primitive with props/children rather than copy-pasting its source into a one-off component.
- Keep Tailwind classes consistent with the `neutral` base palette and existing spacing scale — don't introduce arbitrary colors/spacing values when a token already covers it.

## When a component is genuinely missing

Build it as a thin composition of existing primitives in `resources/js/components/`, following the same file/prop conventions as siblings (see `frontend-refactoring` skill for extraction guidance).
