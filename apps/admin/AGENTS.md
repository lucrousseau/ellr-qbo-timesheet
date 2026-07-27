# Admin (React)

Interface d'administration : connexion QuickBooks Online, statut OAuth, configuration.

Voir aussi le [README racine](../../README.md) et [`packages/api-client/AGENTS.md`](../../packages/api-client/AGENTS.md).

## Stack

- React 19 + TypeScript 6 + Vite 8 + Tailwind CSS 4
- Port dev : **5173**
- API : `@ellr/api-client` (`VITE_API_URL`, défaut `http://localhost:8000/api`)

## Commandes

```bash
npm run dev --workspace=admin
npm run lint --workspace=admin
npm run typecheck --workspace=admin
npm run test --workspace=admin
npm run test:coverage --workspace=admin
npm run test:mutation --workspace=admin
npm run build --workspace=admin
```

## Conventions

- Appels API via `@ellr/api-client`, jamais de fetch direct vers QBO.
- Composants fonctionnels ; logique réseau dans le package partagé.
- Classes Tailwind utilitaires, pas de CSS custom sauf `index.css`.
- Tests : `*.test.tsx` à côté du code testé.

## Fichiers clés

```
src/App.tsx          # Page principale admin
src/App.test.tsx     # Tests composants (mock @ellr/api-client)
src/test/setup.ts    # Setup Vitest + cleanup
```

## Tests obligatoires

- Tout changement UI visible : mettre à jour `App.test.tsx`.
- Changement client HTTP partagé : `packages/api-client/src/api.test.ts`.

## Seuils qualité

- Couverture : 85 % lignes/stmts/funcs, 75 % branches.
- Mutation Stryker : break ≥ 55 %, low ≥ 65 %, high ≥ 80 % (validé, score ~85 %).
