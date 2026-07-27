# Ellr QBO Timesheet

Monorepo timesheet minimaliste pour QuickBooks Online.

## Structure

| Dossier | Rôle | Stack |
|---------|------|-------|
| `backend/` | API REST Laravel | PHP 8.3, Laravel 13, Sanctum, SDK QBO |
| `packages/api-client/` | Client HTTP partagé | TypeScript, Vitest, Stryker |
| `apps/admin/` | Interface admin (connexion QBO) | React 19, Vite 8, Tailwind 4 |
| `apps/timesheet/` | Saisie de temps utilisateur | React 19, Vite 8, Tailwind 4 |

Les frontends importent `@ellr/api-client` et parlent **uniquement** à l'API Laravel (`/api/*`). Seul le backend communique avec QuickBooks Online.

## Commandes essentielles

```bash
# Développement
npm run dev:api          # Laravel :8000
npm run dev:admin        # Admin :5173
npm run dev:timesheet    # Timesheet :5174

# Qualité (local, Husky)
npm run lint             # oxlint + Pint
npm run typecheck        # TypeScript (apps + api-client)
npm run test:coverage    # Vitest + Pest avec seuils 85 %
npm run qa               # lint + types + couverture + Behat + builds
npm run qa:finance       # validate:thresholds (couverture + mutation)

# Backend
cd backend && composer test
cd backend && composer test:behat
cd backend && composer analyse
cd backend && composer test:mutation
```

## Règles pour l'agent

1. **Ne pas lire le code pour valider** : faire passer les tests et les hooks Husky.
2. **Ne pas committer** sauf demande explicite de l'utilisateur.
3. Toute modification backend dans `app/` doit avoir un test Pest associé (`covers(ClassName::class)` pour la mutation).
4. Toute route API dont le comportement change doit avoir un scénario Behat si applicable.
5. Toute modification HTTP partagée : `packages/api-client/` + `api.test.ts`.
6. Pas de communication directe frontend → QBO : tout passe par `backend/`.
7. Ne jamais committer `.env`, tokens, ou secrets Intuit.
8. Respecter Pint (PHP) et oxlint (TS/TSX) avant de conclure une tâche.
9. Avant release : `npm run qa:finance` (couverture + mutation). Avant push : les hooks `prepush` suffisent pour le quotidien.

## Hooks Git (Husky)

| Hook | Commande | Vérifie |
|------|----------|---------|
| `pre-commit` | `npm run precommit` | Lint + format (Pint) + typecheck |
| `pre-push` | `npm run prepush` | Lint + typecheck + couverture 85 % + arch + Behat + PHPStan + builds Vite |

La mutation testing n'est pas dans les hooks (trop lent). Lancer `npm run qa:finance` avant release.

## Seuils qualité (finance)

| Métrique | Backend | Frontend |
|----------|---------|----------|
| Couverture | 85 % (Pest) | 85 % lignes, 75 % branches (Vitest) |
| Mutation | ≥ 80 % (Pest `--mutate`, ~87 %) | break ≥ 55 %, **low ≥ 65 %**, high ≥ 80 % (Stryker) |

Scores Stryker mesurés : admin ~85 %, timesheet ~91 %, api-client ~93 %.

## Cursor

- Rules : `.cursor/rules/` (`monorepo-commands.mdc` toujours actif ; `git-and-pr-workflow.mdc`, `copywriting.mdc`)
- Revue PR : `.cursor/BUGBOT.md`
- Détails par zone : `backend/AGENTS.md`, `packages/api-client/AGENTS.md`, `apps/*/AGENTS.md`
