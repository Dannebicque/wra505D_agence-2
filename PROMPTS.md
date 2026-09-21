# Prompts de démarrage

À copier-coller dans Claude Code. Aucun des prompts ci-dessous ne répète les règles de
`CLAUDE.md` : elles sont déjà chargées à chaque session, les redire coûte des jetons pour rien.

---

## 1. Première session du projet — initialisation

> Lis `CLAUDE.md` et rien d'autre pour l'instant.
>
> Tâche : initialiser le projet Nuxt 4 du volet 2 (espace étudiant), sur une branche
> `feature/0-project-setup` créée depuis `develop`.
>
> Ce que je veux en sortie :
> - projet Nuxt 4 + TypeScript strict + Pinia + ESLint `@nuxt/eslint` + Prettier + Vitest, configuré et fonctionnel ;
> - scripts `lint`, `lint:fix`, `typecheck`, `test`, `build`, `dev` dans `package.json` ;
> - arborescence vide mais posée : `components/`, `composables/`, `pages/`, `server/api/`, `types/`, `assets/styles/` ;
> - `assets/styles/tokens.css` avec les variables CSS des jetons de DA listés dans `CLAUDE.md`, en clair et en sombre ;
> - `composables/useApi.ts` : couche d'accès unique, aujourd'hui branchée sur `server/api/`, avec un point de bascule commenté pour la future API réelle ;
> - `types/domain.ts` : types du domaine (`Etudiant`, `Groupe`, `Semestre`, `Matiere`, `Note`, `Absence`, `Document`, `Evenement`), sans inventer de champ non nécessaire.
>
> Pas de page de démonstration, pas de composant d'exemple, pas de README. Quand tout passe,
> ouvre la PR vers `develop`.

---

## 2. Première session pour un développeur qui rejoint

> Lis `CLAUDE.md`. Ne lis aucun autre fichier.
>
> Réponds en cinq lignes maximum : sur quelle branche je dois partir, comment je nomme ma branche
> et mes commits, qui valide ma PR, et ce qui bloque une PR.
>
> Ensuite, attends ma tâche.

---

## 3. Nouvelle fonctionnalité

Utiliser la commande `/feature`, qui contient déjà le déroulé complet :

> /feature Palette de recherche universelle ouverte par Ctrl+K, cherchant dans les documents,
> les matières, les personnes et les cours, tolérante aux fautes de frappe, entièrement
> navigable au clavier.

Forme générale d'une bonne demande de fonctionnalité : une phrase qui décrit le comportement
attendu du point de vue de l'étudiant, puis les cas limites qui doivent être traités, puis ce
qui est explicitement hors périmètre.

Exemple complet :

> /feature Vue documentaire par matière.
>
> Comportement : depuis la page Documents, l'étudiant peut filtrer par matière, par semestre et
> par type de document, cumulativement. Les filtres actifs sont dans l'URL pour que la vue soit
> partageable. Trois blocs en haut : récemment ajoutés, épinglés par l'étudiant, nouveautés
> depuis sa dernière visite.
>
> Cas limites : aucun document, aucun résultat après filtrage, document sans matière rattachée,
> liste de plus de deux cents documents.
>
> Hors périmètre : l'écran enseignant de rattachement d'un document à une matière, et toute
> modification du backoffice existant.

---

## 4. Revue de PR (PO sur `develop`, QA sur `main`)

> /review 42

---

## 5. Contrôle d'accessibilité

> /a11y components/search/SearchPalette.vue

---

## 6. Reprise de session après une pause

> Lis `CLAUDE.md`. Donne-moi en trois lignes l'état de la branche courante : ce qui est fait, ce
> qui reste, ce qui ne passe pas. N'ouvre aucun autre fichier que ceux nécessaires pour
> répondre.

---

## Ce qu'il ne faut pas faire dans un prompt

- Recopier les règles de `CLAUDE.md`. Elles sont déjà là.
- Demander « fais-moi l'espace étudiant ». Trop large : la session part dans tous les sens et
  consomme le contexte en exploration.
- Demander à l'agent de lire `docs/` au démarrage. Ces fichiers sont longs ; on les cite
  nommément quand la tâche en a besoin.
- Enchaîner trois fonctionnalités dans la même session. Une session, une branche, une PR.
- Demander un récapitulatif. Le diff et la PR suffisent.
