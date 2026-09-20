---
paths:
  - "apps/**/*.{ts,tsx}"
  - "packages/**/*.{ts,tsx}"
  - "backend/app/**/*.php"
---

# File structure

Canonical detail: `.cursor/rules/file-structure.mdc`.

| Layer | Target | Extract when |
|-------|--------|--------------|
| `apps/*/src/App.tsx` | ≤ 80 lines | Screen logic → `hooks/`, JSX → `components/` |
| `apps/*/src/components/*.tsx` | ≤ 120 lines | Sub-sections or repeated markup |
| `apps/*/src/hooks/*.ts` | ≤ 150 lines | Split by domain |
| `packages/api-client/src/*.ts` | ≤ 150 lines | One module per domain |
| `packages/ui/src/**` | ≤ 120 lines | Keep shared components small |
| API controllers | ≤ 124 lines (Pest Arch `< 125`) | Logic → services |
| `backend/app/Services/**` | ≤ 250 lines | Split only on a real domain boundary |

`App.tsx`: loading → login → dashboard wiring. Hooks own API calls. Components receive props; no `apiFetch` unless the file is a dedicated data container.

Do not extract a private controller helper whose only job is `$request->user()` plus token resolve on a 5-action CRUD class already near the Arch limit.
