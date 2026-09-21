# CLAUDE.md

Règles du projet. Elles s'appliquent à chaque session, sans exception et sans rappel.

## Projet

Agence web de 3 personnes. Deux volets, à ne jamais mélanger :

- **Volet 1 — site vitrine de l'agence.** Identité libre. Contenu : services, réalisations,
  équipe, contact.
- **Volet 2 — espace étudiant de l'intranet de l'IUT de Troyes.** Client réel. Direction
  artistique imposée. Périmètre : partie étudiante uniquement.

Priorités fonctionnelles du volet 2, dans cet ordre : recherche universelle tolérante aux fautes,
réorganisation documentaire par matière et par SAE, tableau de bord recentré sur l'instant,
emploi du temps mobile, normalisation du design system.

## Stack imposée

Nuxt 4 · Vue 3 Composition API · TypeScript strict · Pinia · Nitro pour l'API simulée ·
Vitest pour l'unitaire · Cypress pour l'E2E · ESLint `@nuxt/eslint` + Prettier.

Aucun autre framework, aucune librairie UI lourde. Les composants sont écrits à la main.

## Interdits

- Commiter sur `main` ou `develop`. Jamais, sous aucun prétexte.
- Ajouter une dépendance sans me le demander d'abord.
- Utiliser `any`, `@ts-ignore`, `eslint-disable` sans justification écrite dans la PR.
- Emoji. Nulle part : code, commits, PR, interface, documentation.
- Commentaire qui paraphrase le code. Un commentaire justifie un choix non évident, sinon il
  n'existe pas.
- Code mort, `console.log` livré, fichier généré « au cas où », README non demandé.
- Implémenter ce qui n'est pas dans la tâche en cours.
- Modifier le dépôt intranetV3 ou quoi que ce soit de l'intranet réel. On construit à côté.

## Git

GitFlow. `main` et `develop` sont protégées.

- Toute branche part de `develop` : `feature/<slug>`, `release/x.y.z`, `hotfix/<slug>`.
- Une tâche, une branche, une PR. PR courte et relisable.
- PR vers `develop` : validée par le PO. PR vers `main` : validée par le QA.
- Commits en anglais, format Conventional Commits : `type(scope): subject`.
  Types : `feat` `fix` `docs` `style` `refactor` `perf` `test` `chore`.
  Exemple : `feat(search): add fuzzy matching on student names`.
- Un commit = un changement cohérent. Pas de commit fourre-tout.

## Code

- Composants Vue en PascalCase, SFC avec `<script setup lang="ts">`.
- Composables en camelCase préfixés `use` : `useStudentGrades.ts`.
- Dossiers en kebab-case. Organisation par domaine, pas par type technique.
- Types partagés dans `types/`. Jamais le même type écrit à deux endroits.
- Tout accès aux données passe par une couche unique (`composables/useApi.ts`). Les composants
  n'appellent jamais `$fetch` directement : le jour où la vraie API arrive, on ne change que
  cette couche.
- Données simulées dans `server/api/` via Nitro, modélisées sur le domaine réel
  (`Etudiant`, `Groupe`, `Semestre`, `Matiere`, `Note`, `Absence`, `Document`, `Evenement`).

## Accessibilité

Non négociable : l'IUT est un établissement public, le RGAA s'applique.

- HTML sémantique avant ARIA.
- Tout champ a un `label` associé.
- Tout élément interactif est atteignable et actionnable au clavier, avec focus visible.
- Une icône décorative est masquée : `aria-hidden="true"`.
- Contraste minimum 4,5:1 sur le texte, 3:1 sur les éléments d'interface.
- Cible tactile minimum 44 x 44 px.
- Un composant sans vérification clavier et contraste n'est pas terminé.

## Tests

- Toute logique métier a un test unitaire.
- Tout parcours utilisateur majeur a un test E2E.
- On ne corrige pas un test pour le faire passer : on corrige le code.

## Terminé

Une tâche est terminée quand : le build passe, le lint passe, les tests passent, le clavier et
les contrastes ont été vérifiés, et la PR est ouverte avec son template rempli.

## Comportement attendu

Ces règles existent pour économiser du contexte et des jetons. Elles comptent autant que les
autres.

- Ne lis que les fichiers nécessaires à la tâche. Jamais un dossier entier par précaution.
- N'ouvre `docs/` que si la tâche l'exige, et seulement le fichier concerné.
- Pas de préambule, pas de « je vais maintenant », pas de reformulation de ma demande.
- Pas de récapitulatif en fin de tâche. Le diff est le compte rendu.
- Réponses courtes. Le code est le livrable, pas le texte autour.
- Ne relance pas le serveur de développement ou le build en boucle pour vérifier.
- Si une règle métier est ambiguë, arrête-toi et demande. N'invente pas de comportement produit.
- Si tu constates qu'une règle de ce fichier est fausse ou obsolète, dis-le en une ligne au lieu
  de la contourner.

## Où est l'information

À n'ouvrir que sur demande explicite ou quand la tâche le nécessite :

| Fichier | Contenu | Quand l'ouvrir |
|---|---|---|
| `docs/PROJET.md` | dossier de suivi complet, décisions, échéances | cadrage, pitch, arbitrage de périmètre |
| `docs/01-audit-existant.md` | audit de l'intranet, constats numérotés, jetons de DA | travail sur l'UI, l'accessibilité, la recherche, les documents |
| `docs/02-questions-client.md` | questions au client et réponses obtenues | doute sur un besoin |

Jetons de direction artistique à respecter, volet 2 uniquement :
primaire `#F7B000`, texte sur primaire `#4D3677`, texte courant `#4D5259`,
fond `#F5F6FA`, succès `#15C377`, danger `#F96868`, rayon `6px`, police Roboto.
Le vert et le rouge ne portent jamais de texte blanc : contraste insuffisant, corrigé chez nous.
