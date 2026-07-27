# API Client (`@ellr/api-client`)

Client HTTP TypeScript partagé par `apps/admin` et `apps/timesheet`. Seul point d'accès réseau côté frontend vers l'API Laravel.

## Fichiers

```
src/
├── api.ts          # apiFetch, ApiError, getApiErrorMessage, ensureCsrfCookie
├── auth.ts         # login, logout, fetchCurrentUser, updateQboEmployee, type User
├── quickbooks.ts   # fetchQuickBooksStatus, connect/disconnect, OAuth callback helpers
├── timesheet.ts    # createTimeActivity
├── index.ts        # Ré-exports publics
└── api.test.ts     # Tests unitaires (obligatoires si api.ts ou auth.ts change)
```

## Commandes

```bash
npm run lint --workspace=@ellr/api-client
npm run typecheck --workspace=@ellr/api-client
npm run test --workspace=@ellr/api-client
npm run test:coverage --workspace=@ellr/api-client
npm run test:mutation --workspace=@ellr/api-client
```

## Seuils

| Métrique | Seuil |
|----------|-------|
| Couverture (Vitest) | 85 % lignes/stmts/funcs, 75 % branches |
| Mutation (Stryker) | break ≥ 55 %, low ≥ 65 %, high ≥ 80 % (validé, score ~93 %) |

## Conventions

- `apiFetch` : `credentials: 'include'` par défaut (Sanctum stateful), CSRF sur mutations.
- `getApiErrorMessage` : messages utilisateur en français, codes métier (`quickbooks_not_connected`, `registration_disabled`, `qbo_employee_not_configured`, etc.) : **seule** source de vérité côté UI (DRY).
- `ApiError` : exposer `status` et `code` pour le mapping côté UI.
- Pas d'appel vers Intuit/QBO : uniquement `VITE_API_URL`.
- Exports publics limités via `index.ts` (interface segregation).
- Modules domaine (`quickbooks.ts`, `timesheet.ts`) : les apps appellent ces fonctions, pas `apiFetch` directement.

## Tests obligatoires

| Changement | Test requis |
|------------|-------------|
| `apiFetch` / headers / erreurs | `api.test.ts` |
| Auth (login, logout, user) | `api.test.ts` (section auth helpers) |
| Nouveau export public | `api.test.ts` + mise à jour `index.ts` |

Les apps mockent `@ellr/api-client` dans leurs tests composants ; ne pas dupliquer la logique HTTP dans les apps.
