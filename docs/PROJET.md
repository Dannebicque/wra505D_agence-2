# Dossier de suivi — Agence web & refonte de l'espace étudiant de l'Intranet IUT

> Document vivant. Il centralise toutes les réflexions du projet (site agence + projet client) pour qu'on ne reparte jamais de zéro. Tout ce qui est marqué **[À compléter]** doit être rempli ou validé en équipe. Mettez-le à jour à chaque décision, chaque réunion, chaque nouvelle idée.

**Statut au 21/09/2026 :** stack tranchée (Nuxt + Vue), conventions de code définies, audit de l'existant réalisé et intégré. Priorité absolue : préparer le pitch du **22/09/2026 à 12h00** (voir section 2).

---

## Sommaire

1. [Contexte du sujet](#1-contexte-du-sujet)
2. [État d'avancement et priorités immédiates](#2-état-davancement-et-priorités-immédiates)
3. [Volet 1 — Site vitrine de l'agence](#3-volet-1--site-vitrine-de-lagence)
4. [Volet 2 — Client IUT : refonte de l'espace étudiant](#4-volet-2--client-iut--refonte-de-lespace-étudiant)
   - 4.1 [Rappel du besoin](#41-rappel-du-besoin)
   - 4.2 [Audit de l'existant (version 1, sprint 0)](#42-audit-de-lexistant-version-1-sprint-0)
   - 4.3 [Contraintes d'intégration](#43-contraintes-dintégration)
   - 4.4 [Brainstorm fonctionnalités — matière pour le Miro](#44-brainstorm-fonctionnalités--matière-pour-le-miro)
   - 4.5 [Architecture technique envisagée](#45-architecture-technique-envisagée)
   - 4.6 [Accessibilité RGAA](#46-accessibilité-rgaa-obligation-légale)
5. [Conventions techniques & gestion de version](#5-conventions-techniques--gestion-de-version)
6. [Livrables et échéances](#6-livrables-et-échéances)
7. [Trame du pitch du 22 septembre](#7-trame-du-pitch-du-22-septembre)
8. [Points à clarifier](#8-points-à-clarifier)
9. [Journal des décisions](#9-journal-des-décisions)
10. [Ressources et liens](#10-ressources-et-liens)
11. [Glossaire express](#11-glossaire-express)

---

## 1. Contexte du sujet

Le projet comporte **deux volets bien distincts** qu'il ne faut pas mélanger :

| | Volet 1 — Site agence | Volet 2 — Projet client |
|---|---|---|
| Quoi | Site vitrine de notre agence de dev web | Refonte de l'espace étudiant de l'Intranet IUT |
| Pour qui | Notre propre communication (prospects, jury) | Un vrai client : le responsable des outils de l'IUT |
| Identité graphique | **Libre**, c'est la nôtre | **Imposée** : garder exactement la DA existante |
| Contrainte technique | Doit obligatoirement utiliser un outil Jamstack, à justifier | S'intégrer dans une structure de base déjà existante |
| Fonctionnalités | Services, réalisations, équipe, contact | Libres — le client n'a pas d'idées préconçues, c'est à nous de proposer |

Règle du jeu de classe : **chaque agence (= chaque groupe) doit utiliser un outil Jamstack différent** — à vérifier auprès du formateur ou des autres groupes avant de figer notre choix (voir section 8).

### Le client et sa demande

Le client est le responsable des outils informatiques de son IUT. Il veut moderniser un Intranet existant et répondre aux attentes des étudiants actuels. Points clés du brief :

- Il a **déjà une structure de base** — on doit s'y intégrer, pas repartir de zéro.
- Notre lot = **la partie étudiante**. D'autres parties (personnel, administration...) sont probablement traitées ailleurs (autre groupe, ou existant conservé tel quel — à confirmer).
- **Aucune idée préconçue côté client** sur les fonctionnalités : c'est à nous de driver la réflexion (d'où l'intérêt du Miro).
- Une **structure d'API pourra être communiquée plus tard** : on doit donc concevoir une architecture qui fonctionne dès maintenant avec des données de démonstration, et qui pourra brancher une vraie API sans tout reconstruire.
- Un **"template" de base existe déjà** — confirmé depuis par l'audit de la section 4.2 : il s'agit bien de l'application intranetV3 réelle.

---

## 2. État d'avancement et priorités immédiates

**Le pitch client est demain, mardi 22 septembre 2026 à 12h00.** Ordre de priorité réaliste pour les prochaines heures :

1. **[x] Audit de l'existant réalisé et intégré** (section 4.2) — reste à couvrir avant la fin du sprint 1 : écrans Agenda, Applications, Trombinoscope et Messagerie, parcours utilisateurs chronométrés (tri par cartes, test d'arborescence), audit RGAA formel au lecteur d'écran.
2. **[ ] Finaliser le brainstorm fonctionnalités** (section 4.4, nourri par les opportunités identifiées dans l'audit) → l'importer dans le tableau Miro.
3. **[ ] Construire le tableau Miro** avec au minimum : mapping de l'existant, brainstorm fonctionnalités priorisé, ébauche d'arborescence de l'espace étudiant.
4. **[ ] Préparer le pitch** à partir de la trame de la section 7.
5. **[x] Outil Jamstack tranché : Nuxt (Vue)** (section 3.4). **[ ] Nom de l'agence** encore à choisir (section 3.1).
6. **[ ] Répartir les rôles dans l'équipe**, y compris les rôles **QA** et **PO** requis par le workflow Git (section 5.6) — [À compléter].
7. Le développement du site agence et du prototype démarrent après le pitch, sur la base des retours du client.

Ne pas perdre de temps sur le code avant le pitch : ce qui est jugé demain, c'est la **compréhension du besoin** et la **proposition**, pas une réalisation finie.

---

## 3. Volet 1 — Site vitrine de l'agence

### 3.1 Identité de l'agence

- **Nom** : `Agence 2` proposé par défaut dans le sujet, mais libre. Quelques pistes si on veut un nom plus marquant : **[À compléter / à choisir en équipe]**
  - *Atelier Nova* — sobre, orienté "atelier numérique"
  - *Yuzu Digital* — moderne, frais, facile à décliner en identité visuelle
  - *Kaneva* (clin d'œil à "canevas") — évoque la conception/le prototypage
- **Positionnement / ton** : [À compléter] (ex. agence étudiante spécialisée Jamstack, orientée secteur public/éducation, performance et accessibilité comme argument différenciant — cohérent avec un client comme l'IUT).
- **Services proposés** (à afficher sur le site) :
  - Conception de sites Jamstack (vitrine, portails, intranets)
  - Intégration web responsive & accessible (RGAA/WCAG)
  - Refonte / modernisation de systèmes existants
  - Intégration avec API / CMS headless
- **Équipe** : [À compléter — prénoms, rôles (dev front, UX/UI, intégration, chef de projet), + attribution des rôles **QA** et **PO** exigés par GitFlow, voir section 5.6]
- **Coordonnées** : [À compléter — email de contact, réseaux, éventuel formulaire de contact]

### 3.2 Portfolio / réalisations

Le projet IUT sera notre première référence client. Même en cours de réalisation, il peut apparaître dans le portfolio du site agence ("projet en cours" ou étude de cas), ce qui donne un vrai contenu concret plutôt que des faux projets.

### 3.3 Arborescence du site agence

Proposition de base à ajuster :

```
/                → Accueil (accroche, mise en avant des services, CTA contact)
/services        → Détail des prestations
/realisations    → Portfolio (dont le projet IUT)
/realisations/:slug → Étude de cas détaillée
/equipe           → Présentation de l'équipe
/contact          → Formulaire + coordonnées
```

### 3.4 Outil Jamstack retenu : Nuxt + Vue

**Décision d'équipe :** Nuxt (bundler Vite intégré) avec Vue 3 comme bibliothèque d'interface — sur l'ensemble du projet, site agence comme projet client.

| Outil | Rendu | Points forts | Limites pour notre cas |
|---|---|---|---|
| **Nuxt (retenu)** | Universel (SSR par défaut), SSG (`nuxt generate`), ou rendu hybride par route (`routeRules`) | Écosystème Vue visé par l'équipe, très bon DX, rendu hybride adapté au futur espace étudiant connecté, Vite pour un dev rapide | Écosystème un peu plus restreint que React sur certains sujets très spécifiques |
| Next.js | SSG/SSR/ISR | Très mature, énorme écosystème | Impose React, hors périmètre ici |
| Astro | SSG par défaut, îlots interactifs | Zéro JS par défaut, très léger | Moins pertinent si toute l'équipe travaille en Vue |
| SvelteKit | SSG/SSR | Léger, excellent DX | Impose Svelte, hors périmètre ici |
| Eleventy / Hugo | SSG pur | Ultra rapide, simple | Pas adapté à des pages authentifiées avec données personnalisées (espace étudiant) |

**Justification à présenter au pitch :** le site agence est un site de contenu classique, mais le projet client final (espace étudiant) aura besoin de pages connectées avec données personnelles (notes, planning...). Nuxt couvre les deux besoins avec un seul outil (statique pour les pages publiques, rendu à la demande pour les pages personnalisées), tout en s'appuyant sur Vue que l'équipe a choisi de maîtriser. L'existant réel côté client (voir 4.2) est aujourd'hui un rendu serveur classique (Symfony/Twig) sans API JSON exposée : Nuxt permet de démarrer sur des données mockées puis de basculer proprement sur une vraie API une fois le contrat défini avec le client.

### 3.5 Accessibilité & responsive

- Breakpoints à définir (mobile / tablette / desktop) — mobile-first recommandé.
- Contrastes AA minimum (idéalement AAA sur le texte courant), navigation clavier complète, attributs `alt` sur toutes les images, structure de titres logique, formulaires avec `label` associés.
- Outils de vérification à prévoir en cours de projet : Lighthouse, axe DevTools, WAVE, contrôle clavier manuel.
- C'est un argument de vente fort pour le pitch client : un IUT est un établissement public, donc doublement concerné par l'accessibilité (voir 4.6 — RGAA).

---

## 4. Volet 2 — Client IUT : refonte de l'espace étudiant

### 4.1 Rappel du besoin

Moderniser l'Intranet de l'IUT, répondre aux attentes des étudiants actuels, s'intégrer dans une structure de base déjà existante, proposer spécifiquement **la partie étudiante**. Fonctionnalités libres. API possible plus tard.

### 4.2 Audit de l'existant (version 1, sprint 0)

> **Confirmation :** contrairement à la simple hypothèse posée en première analyse (rapprochement via le lien "Aide" de la page de connexion), l'audit ci-dessous a été réalisé directement sur un compte étudiant réel de l'application, département MMI, semestre 5, version observée **3.19.68**. Cela confirme sans ambiguïté qu'**intranetV3** (David Annebicque, https://github.com/Dannebicque/intranetV3) est bien le système existant à faire évoluer.

Le périmètre audité couvre le département MMI ; l'application dessert aussi GEA, TC, GMP, GEII et CJ en DUT/BUT/LP — les constats ci-dessous sont à revalider si des écrans diffèrent selon la filière.

#### Méthode et limites

Audit exploratoire mené sur un compte étudiant réel, sur les écrans accessibles sans droit d'administration : tableau de bord, documents, recherche globale. Trois postures de test : navigation desktop, navigation mobile émulée (375 x 812), et inspection du DOM pour les mesures de contraste et la structure sémantique. Les mesures de contraste sont calculées selon la formule WCAG 2.x sur les couleurs effectivement appliquées par le navigateur.

Ce que cet audit ne couvre pas encore, et qui doit l'être avant la fin du sprint 1 :

- les écrans Agenda, Applications, Trombinoscope et Messagerie,
- les parcours réels chronométrés auprès d'étudiants (tri par cartes et test d'arborescence),
- un audit RGAA formel avec un lecteur d'écran.

#### Socle technique

| Élément | Observation |
|---|---|
| Backend | Symfony 6.x, rendu serveur Twig |
| Assets | Webpack Encore, chunks versionnés sous `/build/` |
| Routing exposé au JS | FOSJsRoutingBundle, 167 routes publiées |
| CSS | Bootstrap 5, thème administrateur |
| Police | Roboto |
| Icônes | police à ligatures (Material) |
| Multi-établissement | classe CSS de site sur `<body>` (`troyes`) |
| Source du code | dépôt public Dannebicque/intranetV3 |
| Sources de données | Celcat pour les emplois du temps, Apogée pour la scolarité |

> **Conséquence directe :** le modèle de données, les contrôleurs et les gabarits sont lisibles publiquement. L'équipe n'a pas besoin d'attendre une livraison du client pour cartographier le domaine.

**Domaine métier déduit du routing :** absences, notes, evaluation, edt, matiere, groupe, etudiant, personnel, stage, alternance, apc (approche par compétences BUT), trombinoscope, messagerie, actualite, reservation, borne (probablement des bornes physiques d'accueil/impression du département), agenda, recherche.

**Complément relevé indépendamment sur le dépôt public** (outillage qualité déjà en place côté backend, à prendre comme repère pour nos propres exigences — voir section 5) : PHPUnit, Cypress, PHP-CS-Fixer, PHPStan, Rector, ESLint, Qodana, CodeClimate, CI/CD via GitHub Actions, licence **MPL-2.0**.

#### Jetons de direction artistique relevés

| Rôle | Valeur |
|---|---|
| Primaire (orange) | `#F7B000` |
| Texte sur primaire | `#4D3677` (violet foncé) |
| Texte courant | `#4D5259` |
| Fond d'application | `#F5F6FA` |
| Succès | `#15C377` |
| Danger | `#F96868` |
| Typographie | Roboto, corps 13 à 15 px, graisse 300 à 400 |

> Un mode sombre existe déjà dans le menu utilisateur : à prendre en compte dès la conception du design system, pas après.

**Compléments relevés par l'agence sur la page de connexion** (hors périmètre de l'audit compte étudiant) :

- Rayon des angles : `6px` sur les boutons.
- Logo : "IUT Troyes" (pli/flèche orange + wordmark) — à récupérer en SVG auprès du client plutôt qu'à recréer à la main.
- Visuel de la page de connexion : photo du bâtiment de l'IUT, assombrie, carte centrée ~450px.
- Pied de page : Aide, Données personnelles, À propos, Mentions légales — aucune déclaration d'accessibilité publique repérée (voir 4.6).

#### Architecture de l'information

Navigation principale : cinq entrées — Dashboard, Trombinoscope, Agenda, Applications, Documents. Menu utilisateur secondaire : Messagerie, Profil, Paramètres, mode sombre.

Deux problèmes de fond :

1. Le vocabulaire est celui de l'institution, pas celui de l'étudiant. "Trombinoscope", "Applications", "Modalités de Contrôle des Connaissances" décrivent des objets administratifs. Un étudiant cherche un cours, une salle, une note, une personne à contacter.
2. Rien ne porte la notion de matière ou de SAE, alors que c'est l'unité dans laquelle l'étudiant organise mentalement toute sa scolarité. Les documents, les notes et l'emploi du temps vivent dans trois silos séparés qui ne se rejoignent jamais.

#### Constats par écran

##### Tableau de bord

Le tableau de bord empile, dans l'ordre : la grille d'emploi du temps de la semaine entière, un tableau d'absences, un tableau de notes, le tableau des modalités de contrôle des connaissances (14 lignes), une liste de liens utiles, puis une liste de contacts.

| # | Constat | Gravité |
|---|---|---|
| TB-1 | Aucune hiérarchie : la grille hebdomadaire complète occupe le premier écran alors que l'information la plus demandée est « mon prochain cours, à quelle heure, dans quelle salle ». | Élevée |
| TB-2 | Le cours en cours et le jour courant ne sont pas mis en évidence dans la grille. | Élevée |
| TB-3 | Les tableaux vides sont rendus intégralement, en-têtes compris, avec une ligne « Aucune note n'a été saisie ». Deux blocs occupent l'écran pour ne rien dire. | Moyenne |
| TB-4 | Le tableau des modalités de contrôle affiche des compétences suivies de nombres entre parenthèses, sans légende. L'information n'est pas interprétable. | Moyenne |
| TB-5 | Le bloc Contacts répète six fois les deux mêmes personnes, une fois par parcours du BUT. | Moyenne |
| TB-6 | Un avertissement de quatre lignes sur la synchronisation Celcat est placé au-dessus de l'emploi du temps, à chaque visite, sans possibilité de le masquer. | Faible |
| TB-7 | Un bouton flottant orange « accueil » se superpose au contenu en haut à droite, sans libellé. | Faible |

##### Documents

Neuf cartes de catégories : Vos documents favoris, Alternance, Cellule handicap, Demonstration Intranet, Documents officiels, Plannings, Relations internationales, Stages informations, Stages offres.

| # | Constat | Gravité |
|---|---|---|
| DOC-1 | Les catégories reflètent l'organigramme de l'établissement, pas la vie de l'étudiant. Aucune entrée « cours », « matière », « SAE ». Un étudiant qui cherche un support de cours n'a aucune piste. | Critique |
| DOC-2 | Les cartes de catégorie sont des `<div>` sans lien, sans tabindex et sans rôle. Elles ne sont ni atteignables au clavier, ni annoncées comme cliquables, et n'ont pas d'URL propre : une catégorie ne peut pas être mise en favori ni partagée. | Critique |
| DOC-3 | Le compteur de documents affiche le libellé « Nb. de documents dans la catégorie » sans la valeur sur huit cartes sur neuf. Aucune indication de volume, de fraîcheur ou de contenu. | Élevée |
| DOC-4 | Pas de recherche interne, pas de filtre, pas de tri, pas de vue « récents », pas de vue « nouveautés ». La seule stratégie possible est l'exploration séquentielle. | Élevée |
| DOC-5 | Hiérarchie de titres incohérente : h1 suivi directement de neuf h5, sans h2. | Moyenne |
| DOC-6 | À 800 px de large, une seule carte par ligne, hauteur d'environ 180 px pour trois mots utiles. Neuf catégories demandent plusieurs écrans de défilement. | Moyenne |

##### Recherche globale

Déclenchée par l'icône loupe ou par le raccourci annoncé `cmd+k`. Appelle `GET /fr/recherche/?q=...`, répond en 50 à 90 ms.

| # | Constat | Gravité |
|---|---|---|
| RECH-1 | Aucune tolérance aux fautes. « annebicque » renvoie deux résultats, « anebicque » en renvoie zéro, sans suggestion de correction. C'est le défaut le plus pénalisant à l'usage. | Critique |
| RECH-2 | Périmètre limité à trois types : Étudiants, Permanents, Documents. Les matières, l'emploi du temps, les actualités et les pages de l'intranet ne sont pas indexés. « developpement front » ne renvoie rien alors que la matière existe. | Critique |
| RECH-3 | Le champ n'a ni `role="combobox"`, ni `aria-expanded`, ni `aria-controls`. La page ne contient aucune région `aria-live` : l'arrivée des résultats n'est pas annoncée aux lecteurs d'écran. | Critique |
| RECH-4 | La surcouche n'a pas de `role="dialog"` et la touche Échap ne la ferme pas. | Élevée |
| RECH-5 | Aucune navigation des résultats au clavier (flèches, Entrée), aucun résultat mis en évidence par défaut. | Élevée |
| RECH-6 | Le champ de saisie est en très grande taille et tronque la requête : au-delà d'une vingtaine de caractères, le début n'est plus visible. | Moyenne |
| RECH-7 | Les résultats « personne » n'affichent que le nom et l'adresse e-mail. Ni rôle, ni département, ni action directe (écrire, voir le profil). | Moyenne |
| RECH-8 | Trois blocs « pas de résultat » sont affichés simultanément, un par catégorie, ce qui remplit l'écran de messages négatifs. | Faible |
| RECH-9 | Des comptes de test apparaissent dans les résultats de production. | Faible |

##### Mobile (375 x 812)

| # | Constat | Gravité |
|---|---|---|
| MOB-1 | En vue jour, le bloc de cours est positionné dans une colonne décalée, sans axe horaire ni en-tête de jour visibles, avec une large zone vide à sa gauche. L'information devient difficile à situer. | Élevée |
| MOB-2 | Deux barres de navigation temporelle empilées, aux libellés et aux styles incohérents (« Semaine » puis « Aujourd'hui »). | Moyenne |
| MOB-3 | Le libellé du bouton « Déposer un justificatif » déborde de son conteneur. | Moyenne |
| MOB-4 | 26 éléments interactifs sur 66 mesurent moins de 44 px dans au moins une dimension, en dessous de la cible tactile recommandée. | Moyenne |
| MOB-5 | L'avertissement Celcat occupe quatre lignes avant tout contenu utile. | Faible |

> Point positif : pas de débordement horizontal, et la balise viewport est correctement configurée.

##### Accessibilité transverse

| # | Constat | Mesure | Gravité |
|---|---|---|---|
| A11Y-1 | Les libellés de navigation contiennent la ligature de l'icône, non masquée aux technologies d'assistance. Un lecteur d'écran annonce « dashboard Dashboard », « group Trombinoscope », « calendar Agenda ». | — | Élevée |
| A11Y-2 | Boutons verts : texte blanc sur `#15C377`. | 2,31:1 (minimum requis 4,5:1) | Élevée |
| A11Y-3 | Boutons rouges : texte blanc sur `#F96868`. | 2,91:1 | Élevée |
| A11Y-4 | Blocs de cours de l'emploi du temps : texte blanc sur fond vert, même famille chromatique. | à confirmer | Élevée |
| A11Y-5 | Aucun lien d'évitement vers le contenu principal. | — | Moyenne |
| A11Y-6 | Trois éléments `<header>` sur une même page, hiérarchie de titres discontinue. | — | Moyenne |

> À l'inverse, et il faut le dire : le texte courant `#4D5259` sur blanc atteint 7,87:1, et le texte violet foncé sur le bouton orange primaire atteint 5,29:1. Les deux passent le niveau AA confortablement. Le choix de ne pas mettre de blanc sur l'orange est une décision juste, qui n'a simplement pas été appliquée aux couleurs vert et rouge. La palette n'est pas à refaire, elle est à compléter et à normaliser.

#### Ce qui fonctionne et doit être conservé

- La palette et l'identité visuelle sont cohérentes et reconnaissables.
- Le contraste du texte courant est excellent.
- La recherche existe déjà, est rapide (50 à 90 ms) et dispose d'un raccourci clavier annoncé.
- Un mode sombre est déjà prévu.
- L'application est déjà responsive dans sa structure, sans débordement horizontal.
- Le domaine métier est riche et complet côté données.

> Le problème n'est pas la qualité du socle. C'est que l'interface expose la structure de la base de données plutôt que les tâches de l'étudiant.

#### Opportunités, classées

| Priorité | Opportunité | S'appuie sur |
|---|---|---|
| 1 | Recherche universelle tolérante aux fautes, multi-types, entièrement accessible au clavier | RECH-1 à RECH-9 |
| 2 | Réorganisation documentaire par matière et par SAE, avec facettes, récents et épinglés | DOC-1 à DOC-6 |
| 3 | Tableau de bord recentré sur « maintenant » : prochain cours, salle, échéances, absences à justifier | TB-1 à TB-7 |
| 4 | Vue emploi du temps mobile repensée | MOB-1 à MOB-5 |
| 5 | Normalisation du design system, contrastes compris, en conservant la direction artistique | A11Y-2 à A11Y-6 |

> Les deux premières correspondent exactement à la demande initiale du client. Les trois suivantes en découlent naturellement et ne nécessitent pas d'élargir le périmètre.

#### Questions ouvertes issues de l'audit

- Les compteurs de documents sont-ils vides parce qu'il n'y a réellement aucun document dans ces catégories, ou s'agit-il d'un défaut d'affichage ?
- Existe-t-il, dans le modèle de données, un lien entre un document et une matière ou une SAE, ou la catégorie est-elle le seul axe de classement disponible ?
- La recherche est-elle volontairement limitée à trois types, ou est-ce un état intermédiaire ?
- Jusqu'où la direction artistique peut-elle évoluer : couleurs et logo conservés mais mise en page libre, ou iso-visuel strict ?
- Le mode sombre doit-il être couvert par notre partie étudiante ?
- Des comptes de test sont visibles dans les résultats de recherche en production : doit-on les considérer comme des données réelles dans nos jeux d'essai ?

*(reportées et fusionnées avec les questions déjà ouvertes du projet en section 8)*

#### Annexe — points d'entrée observés

| Usage | Route |
|---|---|
| Recherche globale | `GET /fr/recherche/?q={terme}` |
| Tableau de bord | `GET /fr/tableau-de-bord` |
| Documents | `GET /fr/document` |
| Agenda, vue semaine | `GET /fr/agenda/{semaine}` |
| Agenda, vue étudiant | `GET /fr/agenda/{semaine}/etudiant` |
| Trombinoscope | `GET /fr/trombinoscope/` |
| Applications | `GET /fr/application` |

> Ces routes renvoient du HTML rendu côté serveur. Elles documentent le périmètre fonctionnel, pas un contrat d'API : le contrat reste à rédiger et à faire valider par le client.

### 4.3 Contraintes d'intégration

- On s'intègre dans une structure de base déjà existante : la navigation globale, les conventions d'URL et la session/authentification doivent rester cohérentes avec le reste de l'Intranet (probablement traité par d'autres lots/groupes).
- Notre périmètre = partie étudiante uniquement. Éviter de refaire les parties personnel/admin.
- Le système réel utilise une **authentification SSO via URCA (CAS)** + un mode "compte invité". Pour la démo, prévoir une couche d'authentification simulée mais avec une interface prête à brancher le vrai SSO plus tard.

### 4.4 Brainstorm fonctionnalités — matière pour le Miro

**Priorités validées par l'audit (section 4.2), à porter en premier dans le Miro :**

- [ ] Recherche universelle tolérante aux fautes, multi-types (étudiants, personnels, documents, matières, emploi du temps, actualités), accessible au clavier
- [ ] Réorganisation documentaire par matière et par SAE, avec facettes, récents et épinglés
- [ ] Tableau de bord recentré sur « maintenant » : prochain cours + salle, échéances, absences à justifier
- [ ] Vue emploi du temps mobile repensée (axe horaire visible, une seule barre de navigation temporelle)
- [ ] Design system normalisé : contrastes AA sur tous les boutons (vert/rouge compris), icônes masquées aux lecteurs d'écran, lien d'évitement, hiérarchie de titres cohérente

**Existant à moderniser (vocabulaire du domaine confirmé par le routing, section 4.2) :**

- [ ] Emploi du temps (edt, sync Celcat)
- [ ] Notes / évaluation par semestre / UE / matière, approche par compétences (APC)
- [ ] Absences + dépôt de justificatif en ligne
- [ ] Stage, alternance, édition des conventions
- [ ] Trombinoscope / annuaire (etudiant, personnel)
- [ ] Messagerie
- [ ] Actualités
- [ ] Réservation (salles/matériel)
- [ ] Agenda

**Pistes complémentaires (brainstorm agence, pas encore validées par l'audit) :**

- [ ] Centre de notifications (nouvelle note, absence à justifier, document déposé)
- [ ] Export/abonnement calendrier (iCal, intégration Google/Outlook/Apple)
- [ ] PWA installable, avec emploi du temps consultable hors-ligne
- [ ] Mode sombre — existe déjà côté existant, à confirmer si dans notre périmètre (question ouverte, section 8)

À prioriser dans le Miro selon deux axes : **valeur pour l'étudiant** / **effort de mise en œuvre**, pour arriver à une short-list "MVP" à présenter au client.

### 4.5 Architecture technique envisagée

- **État réel confirmé par l'audit** : le backend (Symfony/Twig) rend aujourd'hui du HTML côté serveur sur 167 routes ; il n'existe pas de contrat d'API JSON documenté à ce stade. Pas d'hypothèse à faire sur un éventuel API Platform : ce point reste à clarifier avec le client (section 8).
- **Avantage à exploiter dès maintenant** : le code source, les contrôleurs et les gabarits du système existant sont publics (dépôt `Dannebicque/intranetV3`, licence MPL-2.0). On peut cartographier le vrai modèle de données (entités déduites du routing : `Etudiant`, `Groupe`, `Semestre`, `Matiere`, `Note`, `Absence`, `Document`, `Evenement`, `Stage`, `ConventionDeStage`...) sans attendre une livraison du client.
- **En attendant l'API réelle** : construire le frontend sur des données mockées (JSON local ou `json-server`/MSW), modélisées sur ce domaine réel plutôt que sur des suppositions génériques.
- **Couche d'accès aux données isolée** (ex. un composable `useApi`/un module `server/utils` unique) : le jour où le client fournit la vraie structure d'API, on ne change que cette couche, pas les composants.
- **Rendu hybride Nuxt** : pages publiques/génériques en statique (SSG/`routeRules` prerender), pages étudiant connecté (notes, planning personnel) en rendu à la demande (SSR) car les données sont personnalisées et confidentielles.

### 4.6 Accessibilité RGAA (obligation légale)

L'IUT est un établissement d'enseignement supérieur public : son Intranet est donc soumis au **RGAA** (Référentiel Général d'Amélioration de l'Accessibilité), l'équivalent français de WCAG 2.1 pour le secteur public, avec obligation légale de publier une **déclaration d'accessibilité**. Deux constats renforcent l'argument pour le pitch :

- Aucune déclaration d'accessibilité n'apparaît dans le pied de page public du site actuel (Aide / Données personnelles / A propos / Mentions légales uniquement).
- L'audit technique (section 4.2, bloc "Accessibilité transverse") relève des manquements concrets et mesurés : contrastes insuffisants sur les boutons vert/rouge (2,31:1 et 2,91:1 contre 4,5:1 requis), icônes de navigation non masquées aux lecteurs d'écran, absence de lien d'évitement, hiérarchie de titres discontinue, cartes de documents non atteignables au clavier (DOC-2).

Le socle n'est pas mauvais : le texte courant et le bouton primaire orange/violet passent déjà l'AA confortablement (7,87:1 et 5,29:1). Le travail est donc de **normaliser** l'existant plutôt que de tout refaire — un message rassurant et crédible à porter au client.

---

## 5. Conventions techniques & gestion de version

Ces règles s'appliquent dès le premier commit, sur les deux volets du projet.

### 5.1 Stack retenue

- **Framework** : Nuxt (rendu universel par défaut, SSG via `nuxt generate`, ou rendu hybride par route via `routeRules`), bundler Vite intégré.
- **Bibliothèque UI** : Vue 3 (Composition API), sur l'ensemble du projet (site agence et projet client).
- **Langage** : TypeScript recommandé (cohérent avec la rigueur déjà en place côté backend réel : PHPStan/Rector côté PHP — on garde le même niveau d'exigence côté front avec un typage strict).
- **Gestion d'état** : Pinia recommandé pour l'état partagé (session utilisateur simulée, préférences, favoris de documents).

### 5.2 Support : GitFlow

GitFlow est la stratégie de gestion de version retenue. Elle structure le projet autour de plusieurs branches principales :

- **main** : branche de production. On ne travaille jamais directement dessus.
- **develop** : branche d'intégration, où toutes les fonctionnalités sont fusionnées avant de partir en production.
- **feature/** : branches de fonctionnalités, créées depuis `develop`, fusionnées dans `develop` une fois la fonctionnalité terminée.
- **release/** : branches de préparation de version, créées depuis `develop` quand le code est prêt pour une release (corrections, tests), fusionnées ensuite dans `main` **et** `develop`.
- **hotfix/** : branches de correction urgente, créées depuis `main` pour corriger un problème critique en production, fusionnées dans `main` **et** `develop`.

Guide détaillé : [GitFlow Workflow — Atlassian](https://www.atlassian.com/fr/git/tutorials/comparing-workflows/gitflow-workflow)

**Configuration retenue pour notre projet :**

- `main` est **protégée** : aucune modification directe, tout passe par une Pull Request, et **seul le QA peut valider une PR vers `main`**.
- `develop` est **protégée** : aucune modification directe, tout passe par une Pull Request, et **seul le PO peut valider une PR vers `develop`**.
- Les branches `feature/`, `release/` et `hotfix/` sont créées à partir de `develop` par les développeurs, et fusionnées dans `develop` via Pull Request.

Rôles **QA** et **PO** à assigner dans l'équipe (section 3.1) — sans ces rôles définis, aucune PR ne peut être validée.

### 5.3 Convention de nommage des branches (proposition)

Non imposée explicitement par le sujet, mais nécessaire pour appliquer GitFlow proprement — à valider en équipe :

- `feature/nom-court-de-la-fonctionnalite` (ex. `feature/emploi-du-temps`)
- `release/x.y.z` (ex. `release/1.0.0`)
- `hotfix/nom-court-du-bug` (ex. `hotfix/lien-footer-casse`)

### 5.4 Messages de commit — Conventional Commits

Les commits respectent le format [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/), **rédigés en anglais**, pour faciliter la lecture de l'historique et permettre une génération automatique de changelog :

```
<type>(<scope>): <subject>
```

Types les plus courants :

| Type | Usage |
|---|---|
| `feat` | Ajout d'une nouvelle fonctionnalité (= version mineure) |
| `fix` | Correction d'un bug (= patch) |
| `docs` | Documentation (README, commentaires...) |
| `style` | Changements sans impact sur le sens du code (espaces, formatage, point-virgules, CSS...) |
| `refactor` | Refactorisation (ni bug fix, ni nouvelle fonctionnalité) |
| `perf` | Amélioration de performance |
| `test` | Ajout/modification de tests |
| `chore` | Outils de build, configuration, maintenance (ex. mise à jour de dépendances npm) |

Exemple : `feat(planning): add weekly calendar view`

### 5.5 Qualité de code côté front (proposition à valider en équipe)

Pas de règle imposée par le sujet sur ce point précis : proposition alignée avec la rigueur déjà en place côté backend réel (PHPStan/Rector/PHP-CS-Fixer, voir 4.2), transposée au monde Vue/Nuxt :

- **Linting / formatage** : ESLint (config officielle `@nuxt/eslint`) + Prettier, exécutés en pre-commit (Husky + lint-staged) et bloquants en CI avant toute fusion.
- **Typage** : TypeScript en mode strict, pas de `any` non justifié.
- **Nommage** : composants Vue en PascalCase, en composants monofichiers (SFC) avec `<script setup>` (`StudentDashboard.vue`), composables en camelCase préfixés `use` (`useStudentGrades.ts`), dossiers en kebab-case.
- **Structure** : organisation par domaine/fonctionnalité plutôt que par type technique pur, dès que le projet grossit.
- **Tests** : tests unitaires sur la logique métier (Vitest + Vue Test Utils / Testing Library), tests end-to-end sur les parcours critiques avec **Cypress** — cohérent avec l'outil déjà utilisé côté backend réel.

### 5.6 Definition of Done

Une fonctionnalité est considérée terminée quand : le build passe, le lint passe, les tests passent, un contrôle d'accessibilité de base a été fait (clavier + contraste), une revue de code a eu lieu, et la Pull Request respecte les règles GitFlow ci-dessus (validation QA sur `main`, validation PO sur `develop`).

---

## 6. Livrables et échéances

| Livrable | Détail | Échéance |
|---|---|---|
| Site de l'agence | Nuxt/Vue, responsive, accessible : services, réalisations, équipe, contact | [À compléter — date de rendu] |
| Tableau Miro | Réflexions + fonctionnalités (matière : section 4.4) | Avant le pitch du 22/09 |
| Pitch client n°1 | Présentation de la compréhension du besoin + proposition | **Mardi 22 septembre 2026, 12h00** |

---

## 7. Trame du pitch du 22 septembre

Proposition de trame (~10-12 min + questions), à adapter selon le temps réellement imparti :

1. **Présentation de l'agence** (1 min) — nom, positionnement, équipe.
2. **Reformulation du besoin client** (2 min) — montrer qu'on a bien compris l'enjeu (moderniser, répondre aux attentes étudiantes, s'intégrer à l'existant).
3. **Audit de l'existant** (3 min) — DA relevée, constats par écran, ce qui fonctionne et doit être conservé (section 4.2) : c'est la pièce maîtresse pour prouver le sérieux du travail.
4. **Proposition fonctionnelle** (3 min) — opportunités priorisées issues de l'audit + short-list Miro.
5. **Proposition technique** (2 min) — Nuxt/Vue justifié pour le site agence, architecture découplée envisagée pour l'espace étudiant (données mockées en attendant l'API réelle), méthode de travail GitFlow.
6. **Prochaines étapes / roadmap** (1 min) — ce qu'on fait après validation du client, dont la suite de l'audit (sprint 1).
7. **Questions du client.**

Objectif du pitch : obtenir la validation (ou les retours) du client sur le périmètre fonctionnel avant de commencer à développer.

---

## 8. Points à clarifier

### Côté client / formateur

Issues du contexte général du sujet :

- [ ] Qui gère les autres parties de l'Intranet (personnel, administration) — d'autres groupes, ou existant conservé tel quel ?
- [ ] Outils Jamstack déjà choisis par les autres agences de la classe, pour éviter les doublons.
- [ ] Calendrier et format prévus pour la communication de la structure d'API (REST ? GraphQL ? OpenAPI ?) — l'audit confirme qu'il n'existe aujourd'hui que des routes HTML, pas d'API JSON documentée.
- [ ] Conserver l'absence totale de tracking du site actuel, ou introduire une mesure d'audience respectueuse de la vie privée ?

Issues de l'audit (section 4.2) :

- [ ] Les compteurs de documents vides sont-ils réels ou un défaut d'affichage ?
- [ ] Existe-t-il un lien en base entre un document et une matière/SAE, ou la catégorie est-elle le seul axe de classement ?
- [ ] La recherche est-elle volontairement limitée à trois types (étudiants, personnels, documents), ou est-ce un état intermédiaire ?
- [ ] Jusqu'où la direction artistique peut-elle évoluer : couleurs et logo conservés mais mise en page libre, ou iso-visuel strict ?
- [ ] Le mode sombre doit-il être couvert par notre partie étudiante ?
- [ ] Les comptes de test visibles dans les résultats de recherche en production doivent-ils être traités comme des données réelles dans nos jeux d'essai ?

### Interne à l'équipe

- [ ] Nom définitif de l'agence (section 3.1).
- [ ] Attribution des rôles QA et PO (section 5.2).

---

## 9. Journal des décisions

| Date | Décision | Justification | Par qui |
|---|---|---|---|
| 2026-09-21 | Création de ce dossier de suivi | Centraliser les réflexions avant le pitch du 22/09 | — |
| 2026-09-21 | Identification d'intranetV3 (Dannebicque) comme base probable du projet client | Le lien "Aide" de la page de connexion réelle pointe vers la documentation de ce projet open source, correspondant exactement au contexte du sujet | — |
| 2026-09-21 | Stack envisagée un temps en Next.js + React | Choix initial, avant clarification de l'équipe | — |
| 2026-09-21 | Stack définitivement arbitrée en **Nuxt + Vue**, sur l'ensemble du projet | Décision d'équipe, remplace l'hypothèse Next.js/React | — |
| 2026-09-21 | Adoption de GitFlow + Conventional Commits comme méthode de travail Git | Structurer les contributions de l'équipe, PR obligatoires avec validation QA (main) / PO (develop) | — |
| 2026-09-21 | Intégration de l'audit de l'existant (v1, sprint 0) | Audit réalisé sur un compte étudiant réel (MMI, S5, version 3.19.68) : confirme le système existant, fournit des constats mesurés et des opportunités priorisées | Équipe |

*(à compléter à chaque nouvelle décision d'équipe : nom de l'agence, fonctionnalités validées par le client...)*

---

## 10. Ressources et liens

- Intranet réel (référence DA) : https://intranet.iut-troyes.univ-reims.fr
- Dépôt du système existant (intranetV3) : https://github.com/Dannebicque/intranetV3
- Documentation du projet (nécessite un compte, à revérifier) : https://dannebicque.gitbook.io/intranet/
- RGAA (référentiel accessibilité secteur public) : https://accessibilite.numerique.gouv.fr/
- Jamstack (présentation générale) : https://jamstack.org
- Nuxt (documentation officielle) : https://nuxt.com/docs
- Vue.js (documentation officielle) : https://vuejs.org
- Conventional Commits : https://www.conventionalcommits.org/en/v1.0.0/
- GitFlow Workflow (Atlassian) : https://www.atlassian.com/fr/git/tutorials/comparing-workflows/gitflow-workflow
- Tableau Miro : [À compléter — lien à ajouter dès sa création]
- Dépôt de code du projet : [À compléter — une fois le repo Git créé]

---

## 11. Glossaire express

- **Jamstack** : architecture web où le frontend est pré-généré (JavaScript, API, Markup) et communique avec des services via API, plutôt qu'un serveur qui génère chaque page à la volée.
- **SSG** (Static Site Generation) : les pages sont générées à l'avance, au build.
- **SSR** (Server-Side Rendering) : les pages sont générées à la demande, côté serveur, à chaque requête.
- **ISR** (Incremental Static Regeneration) : régénération automatique d'une page statique après publication, sans tout rebuilder.
- **Composable** : fonction réutilisable encapsulant de la logique avec état, convention Vue/Nuxt (préfixe `use`), équivalent des hooks React.
- **SFC** (Single File Component) : composant Vue "monofichier" (`.vue`), qui regroupe template, script et style.
- **RGAA** : Référentiel Général d'Amélioration de l'Accessibilité — norme française d'accessibilité pour les sites publics, basée sur WCAG.
- **WCAG** (Web Content Accessibility Guidelines) : référentiel international d'accessibilité du web, dont le RGAA est la déclinaison française.
- **SSO / CAS** : authentification unique (Single Sign-On) via un serveur central — ici le système URCA de l'université.
- **API headless** : API qui fournit uniquement les données, sans imposer d'affichage, consommée librement par le frontend.
- **GitFlow** : stratégie de branches Git structurant le développement autour de `main`, `develop`, `feature/`, `release/`, `hotfix/`.
- **Conventional Commits** : convention de rédaction des messages de commit (`type(scope): subject`) facilitant la lecture de l'historique et la génération de changelog.
- **PO** (Product Owner) : rôle responsable de la validation fonctionnelle, ici seul habilité à valider les PR vers `develop`.
- **QA** (Quality Assurance) : rôle responsable de la qualité/tests, ici seul habilité à valider les PR vers `main`.
- **APC** (Approche Par Compétences) : cadre pédagogique du BUT organisant la formation autour de compétences à valider plutôt que de matières isolées.
- **SAE** (Situation d'Apprentissage et d'Évaluation) : unité pédagogique du BUT, souvent un projet, autour de laquelle un étudiant organise mentalement une partie de sa scolarité.
