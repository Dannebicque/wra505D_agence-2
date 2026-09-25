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

## Stack

Deux volets, deux stacks. Ne pas les confondre.

**Volet 1 — site vitrine de l'agence.** Next.js, déployé sur Vercel :
<https://figmium.vercel.app>. C'est lui qui porte la contrainte Jamstack du sujet.

**Volet 2 — espace étudiant.** On reprend la base du client,
[IUTTroyes/uniServices](https://github.com/IUTTroyes/uniServices), importée dans `uniservices/`.
On ne réécrit pas ce qui marche, on l'améliore.

Vue 3 Composition API · Vite · PrimeVue · Tailwind · Pinia · axios ·
Vitest pour l'unitaire · Cypress pour l'E2E · ESLint + Prettier.

Côté serveur : Symfony 7 et API Platform, MariaDB, données de fixtures. Jamais la production.

Pas de framework supplémentaire, pas de seconde librairie de composants : on utilise PrimeVue,
déjà en place. Un composant n'est écrit à la main que si PrimeVue n'a pas d'équivalent.

## Interdits

- Commiter sur `main` ou `develop`. Jamais, sous aucun prétexte.
- Ajouter une dépendance sans me le demander d'abord.
- Utiliser `eslint-disable` sans justification écrite dans la PR.
- Emoji. Nulle part : code, commits, PR, interface, documentation.
- Commentaire qui paraphrase le code. Un commentaire justifie un choix non évident, sinon il
  n'existe pas.
- Code mort, `console.log` livré, fichier généré « au cas où », README non demandé.
- Implémenter ce qui n'est pas dans la tâche en cours.
- Modifier le dépôt intranetV3, le dépôt uniServices d'origine, ou quoi que ce soit de
  l'intranet réel. On travaille sur notre copie, dans `uniservices/`.
- Toucher aux écrans enseignant et administratif. Notre périmètre est la partie étudiante.
  Exception : corriger un bug dans leur code back reste permis, on n'y ajoute rien.

## Git

GitFlow. `main` et `develop` sont protégées.

- Toute branche part de `develop` : `feature/<slug>`, `release/x.y.z`. Les branches `hotfix/<slug>` partent de `main`.
- Une tâche, une branche, une PR. PR courte et relisable.
- PR vers `develop` : validée par le PO. PR vers `main` : validée par le QA.
- Commits en anglais, format Conventional Commits : `type(scope): subject`.
  Types : `feat` `fix` `docs` `style` `refactor` `perf` `test` `chore`.
  Exemple : `feat(search): add fuzzy matching on student names`.
- Un commit = un changement cohérent. Pas de commit fourre-tout.

## Code

On adopte les conventions du client. Le code doit se fondre dans le sien, pas cohabiter avec.

- Composants Vue en PascalCase, SFC dans l'ordre `script`, `template`, `style`.
- Méthodes et variables en camelCase. Une méthode qui appelle l'API finit par `Service`.
- Les appels à l'API vivent dans `shared/requests/`, jamais dans un composant. On passe par
  `apiService.js` et `apiCall.js`, qui gèrent l'intercepteur axios et les notifications.
- État partagé dans les stores Pinia de `shared/stores/`.
- Types partagés dans `shared/types/`. Jamais le même type écrit à deux endroits.
- Les alias existent, on s'en sert : `@components`, `@stores`, `@requests`, `@helpers`,
  `@styles`, `@config`, `@images`, `@types`.
- Côté PHP : PSR-12, classes en PascalCase, PHPDoc sur les classes et méthodes. `make cs` (depuis
  `uniservices/back`) le vérifie sur les seuls fichiers modifiés ; on ne reformate jamais le code
  du client en bloc, ses diffs ne s'appliqueraient plus.
- Une page étudiante entre dans le `studentMenu` du manifest de son module, jamais dans le menu du
  personnel. Les données de l'étudiant connecté passent par une route `/api/me/…` sans
  identifiant. Un nouveau type de notification ou de résultat de recherche est une nouvelle
  source. Détail dans `docs/04-reprise.md`, section « Où brancher quoi ».
- Aucune couleur en dur : les jetons du preset (`packages/shell/assets/main.js`).

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
- Tout parcours utilisateur majeur a un test E2E. Il doit passer sur une base de fixtures neuve
  et un Vite froid, comme en CI : n'attendre que ce qui existe par défaut.
- On ne corrige pas un test pour le faire passer : on corrige le code.

## Terminé

Une tâche est terminée quand : le build passe, le lint passe, `make cs` passe, les tests passent,
le clavier et les contrastes ont été vérifiés, la PR est ouverte avec son template rempli et sa
CI est verte. Une PR rouge ne se merge pas : toutes les suivantes héritent de son échec.

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
| `docs/03-backlog.md` | toutes les tâches, réparties en colonnes | choisir sur quoi travailler |
| `docs/04-reprise.md` | état du projet, ce qui est fait et ce qui reste | **au début de chaque session** |

Jetons de direction artistique à respecter, volet 2 uniquement :
primaire `#4D3677`, accent `#F7B000`, texte courant `#4D5259`, fond `#F5F6FA`,
succès `#15C377`, danger `#F96868`, rayon `6px`, police Roboto.

Une seule primaire pour tous les modules, jamais une couleur par module : la palette
`VIOLET_IUT` du preset (`packages/shell/assets/main.js`), dont `#4D3677` est la nuance 500.
Le jaune, primaire d'origine de la DA, sert d'accent (`--accent-color`) : choix de l'équipe,
à revoir si le client s'y oppose.

Le client ne fournit ni valeurs sombres, ni bordures, ni couleurs de texte sur le vert et le
rouge. Celles-ci sont dérivées et mesurées, chaque rapport vérifié contre le seuil RGAA :

| Usage | Clair | Sombre | Rapport |
|---|---|---|---|
| Texte courant sur fond | `#4D5259` | `#E1E3E7` sur `#1B1D21` | 7,29:1 et 13,13:1 |
| Texte atténué | `#676D75` | `#A7ACB4` | 7,40:1 en sombre |
| Surface | `#FFFFFF` | `#25282D` | — |
| Bordure d'élément d'interface | `#7E848D` | `#666B73` | 3,49:1 et 3,15:1 |
| Primaire | `#4D3677`, texte blanc dessus | `#AC95E1` (nuance 400) | 9,95:1 et 6,54:1 sur le fond sombre |
| Texte sur accent | `#4D3677` sur `#F7B000` | idem | 5,29:1 |
| Texte sur succès | `#0B3D26` | idem | 5,33:1 |
| Texte sur danger | `#4A1010` | idem | 5,25:1 |

Le vert et le rouge ne portent jamais de texte blanc : 2,31:1 et 2,91:1, très en dessous du
seuil. L'accent jaune ne fait que 1,74:1 sur le fond clair : il ne peut pas délimiter seul un
élément d'interface, il lui faut une bordure. Cible tactile minimale : 44 px.
