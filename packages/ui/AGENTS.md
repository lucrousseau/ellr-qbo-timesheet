# UI package (`@ellr/ui`)

Shared React components, hooks, i18n, and the Ellr design system primitives.

See also the [root README](../../README.md) and [root AGENTS.md](../../AGENTS.md).

## Design system (Headless UI)

**Primitive layer:** [`@headlessui/react`](https://headlessui.com/) v2 (React 19, unstyled, Tailwind 4).

| Rule | Detail |
|------|--------|
| **Import Headless only in `@ellr/ui`** | Apps use Ellr wrappers, never `@headlessui/react` directly |
| **Style via tokens** | `styles/tokens.ts`, `styles/formTokens.ts`, `styles/selectTokens.ts`, `styles/dialogTokens.ts` (brand colors from `packages/vite-config` `app.css`) |
| **Wrap, do not re-export Headless** | Export Ellr components only |
| **Evolve incrementally** | See `.cursor/rules/ui-design-system.mdc` (mandatory checklist on every UI change) |

### Wrapped components

| Component | Headless primitive | Use when |
|-----------|-------------------|----------|
| `Button` | `Button` | Primary, secondary, danger, link; `size="compact"` for header chrome; `size="icon"` for dense icon actions |
| `EllrLogoMark` | — | Brand pastille + optional wordmark/tagline in shell and auth |
| `TextField` | `Field` + `Label` + `Input` | Text, email, datetime-local (not passwords) |
| `PasswordField` | `Field` + `Label` + `Input` | Passwords with show/hide toggle |
| `MaskedInput` | IMask (`react-imask`) | Formatted numeric entry (duration, phone, etc.) |
| `TextAreaField` | `Field` + `Label` + `Textarea` | Multi-line text, notes, descriptions |
| `StaticSelect` | `Listbox` | Short static lists (locale, enums) |
| `LazySearchCombobox` | `Combobox` | API-backed lists with search (`useLazyApiSelect`) |
| `CheckboxField` | `Checkbox` + `Field` | Boolean toggles (billable, settings) |
| `ConfirmDialog` | `Dialog` | Destructive or irreversible confirmations |

Select panels use `portal={false}` and absolute positioning inside a `relative` wrapper (CSS anchor positioning is not relied on).

### Progressive adoption (Headless inventory)

**Strategy:** wrap primitives on demand when a screen needs them. Do not re-export Headless or bulk-add unused wrappers.

| Status | Planned Ellr name | Headless primitive | Notes |
|--------|-------------------|-------------------|-------|
| P1 | `RadioGroupField` | `RadioGroup` + `Radio` | Mutually exclusive options |
| P1 | `SwitchField` | `Switch` + `Field` | Boolean toggles |
| P2 | `NativeSelectField` | `Select` + `Field` | Styled native `<select>` |
| P2 | `AppDialog` | `Dialog` | Non-destructive detail panels |
| P2 | `DropdownMenu` | `Menu` | Action dropdowns |
| P2 | `PopoverPanel` | `Popover` | Contextual overlays |
| P2 | `DisclosurePanel` | `Disclosure` | Expand/collapse sections |
| P3 | `TabsNav` (optional) | `Tabs` | Only if replacing custom `TabNav` is justified |
| internal | — | `CloseButton`, `Portal`, `FocusTrap`, `Transition` | Used inside wrappers, not public exports |

When adding a wrapper: tokens in `styles/*Tokens.ts`, test file, export in `src/index.ts`, update this table and `.cursor/rules/ui-design-system.mdc`.

### Incremental evolution checklist

On every UI change, agents and contributors should:

1. Replace raw form controls in touched files with Ellr wrappers.
2. Prefer `Button` over `primaryButtonClass` / `secondaryButtonClass` in apps.
3. Add or extend a wrapper when the same control pattern appears twice.
4. Update inventory docs when a wrapper ships.
5. Add tests for new public exports.

### Duplicate interaction guard

Use `useGuardedAction` for submit buttons, connect/disconnect CTAs, and any handler that triggers an API call. It blocks re-entry with a ref (immediate) and exposes `pending` to disable UI. Prefer this over debounce for form actions.

## Commands

```bash
npm run lint --workspace=@ellr/ui
npm run typecheck --workspace=@ellr/ui
npm run test --workspace=@ellr/ui
npm run test:coverage --workspace=@ellr/ui
```

## Conventions

- User-facing copy via `useLocale` / message catalogs (`src/i18n/messages/`).
- **JSDoc** on every public export (`.cursor/rules/jsdoc.mdc`).
- Components ≤ ~120 lines; extract subcomponents or `styles/` tokens when growing.
- Tests: `*.test.tsx` next to the component or under `src/hooks/`.

## Required tests

| Change | Test |
|--------|------|
| New public component | `ComponentName.test.tsx` |
| i18n key used in UI | `en.ts` + `fr.ts` |
| Hook with logic | `hookName.test.ts` |
