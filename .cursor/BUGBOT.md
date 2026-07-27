# Règles de revue (Bugbot / review locale)

## Backend PHP

- Tout fichier modifié dans `backend/app/` doit avoir un test Pest associé (Feature ou Unit).
- Utiliser `covers(ClassName::class)` pour le mutation testing Pest.
- Pas de logique métier dans les controllers : déléguer aux services (SRP, DIP).
- Pas d'appel direct au SDK QBO hors de `QuickBooksService` (DRY).
- Pas de duplication de validation métier ou mapping d'erreurs déjà centralisés.
- Pas de secrets, tokens ou credentials en dur.
- Pas de `eval()`, `exec()`, `shell_exec()`, ou `system()`.

## Frontend React

- Pas de `fetch` direct vers des domaines autres que `VITE_API_URL`.
- Pas de client HTTP dupliqué dans les apps : utiliser `@ellr/api-client` (DRY).
- Pas de messages d'erreur métier dupliqués : `getApiErrorMessage` + codes API.
- Pas de `any` TypeScript sans justification en commentaire.
- Changement dans `packages/api-client/` : mettre à jour `api.test.ts` (et `auth.ts` si applicable).
- Changement UI visible dans une app : test composant requis (`App.test.tsx`).
- Avant d'extraire un composant partagé admin/timesheet : vérifier que la logique n'appartient pas plutôt à `api-client` ou au backend.

## Sécurité QBO

- Tokens OAuth uniquement en base (`quickbooks_tokens`), jamais loggés.
- Routes API sensibles protégées par `auth:sanctum`.
- `ALLOW_REGISTRATION=false` en production pour désactiver l'inscription publique.
- Ne pas exposer les corps d'erreur Intuit en prod (`QUICKBOOKS_EXPOSE_API_ERRORS`).

## Architecture

- Violation des tests d'architecture (`tests/Arch/`) : bloquant.
- Nouveau endpoint sans entrée dans `routes/api.php` documentée : signaler.
- Duplication admin/timesheet ou SDK hors service : voir `docs/dry-reusability-plan.md`.

## Qualité

- Seuils couverture : 85 % backend/frontend, 75 % branches frontend.
- Seuils mutation : backend ≥ 80 % (Pest, ~87 %), frontend low ≥ 65 % / high ≥ 80 % (Stryker).
- Ne pas désactiver PHPStan, Pint, oxlint ou les tests pour faire passer une PR.
- Ne pas committer sans demande explicite de l'auteur de la PR.
- Ne pas suggérer `--no-verify` sur les hooks Husky.
