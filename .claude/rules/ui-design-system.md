---
paths:
  - "packages/ui/**/*.{ts,tsx}"
  - "apps/admin/src/**/*.{ts,tsx}"
  - "apps/timesheet/src/**/*.{ts,tsx}"
---

# UI design system (`@ellr/ui`)

Canonical detail: `.cursor/rules/ui-design-system.mdc`, `packages/ui/AGENTS.md`.

- Import Headless UI only inside `packages/ui`. Apps use Ellr wrappers (`Button`, `TextField`, `PasswordField`, `LazySearchCombobox`, `ConfirmDialog`, and the rest listed in `packages/ui/AGENTS.md`).
- Style shared chrome via `styles/*Tokens.ts`, not ad-hoc Tailwind on raw controls.
- Prefer `Button` variants over `primaryButtonClass` / `secondaryButtonClass`.
- Wrap async submit and connect/disconnect handlers with `useGuardedAction`.
- New UI strings: both `en.ts` and `fr.ts` catalogs.
- On every UI change: replace raw `<input>` / `<textarea>` / `<button>` in the files you touch. Add a wrapper in `@ellr/ui` when the same control appears twice. Test new public exports.
- Do not add shadcn, Radix, or react-select beside Headless without an ADR-level decision.
