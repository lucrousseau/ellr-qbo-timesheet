# Timesheet (React)

Interface utilisateur pour enregistrer du temps. Communique avec l'API Laravel qui pousse vers QBO.

Voir aussi le [README racine](../../README.md) et [`packages/api-client/AGENTS.md`](../../packages/api-client/AGENTS.md).

## Stack

- React 19 + TypeScript 6 + Vite 8 + Tailwind CSS 4
- Port dev : **5174**
- API : `@ellr/api-client` (`VITE_API_URL`, défaut `http://localhost:8000/api`)

## Commandes

```bash
npm run dev --workspace=timesheet
npm run lint --workspace=timesheet
npm run typecheck --workspace=timesheet
npm run test --workspace=timesheet
npm run test:coverage --workspace=timesheet
npm run test:mutation --workspace=timesheet
npm run build --workspace=timesheet
```

## Conventions

- Formulaire de saisie via `POST /api/time-activities` (auth Sanctum requise côté API).
- Appels API via `@ellr/api-client`, jamais de fetch direct vers QBO (DRY).
- Employé QBO : assigné au compte (admin), pas saisi dans le formulaire (règle métier côté API).
- Erreurs : `getApiErrorMessage` uniquement ; pas de messages dupliqués.
- Validation côté backend ; le frontend envoie les champs documentés dans l'API.
- Tests avec Testing Library + Vitest.

## Fichiers clés

```
src/App.tsx          # Formulaire de saisie de temps
src/App.test.tsx     # Tests composants (mock @ellr/api-client)
src/test/setup.ts    # Setup Vitest + cleanup
```

## Tests obligatoires

- Changement formulaire ou soumission : mettre à jour `App.test.tsx`.
- Changement client HTTP partagé : `packages/api-client/src/api.test.ts`.

## Seuils qualité

- Couverture : 85 % lignes/stmts/funcs, 75 % branches.
- Mutation Stryker : break ≥ 55 %, low ≥ 65 %, high ≥ 80 % (validé, score ~91 %).
