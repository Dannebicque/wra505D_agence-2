# Audit de l'existant — Intranet IUT de Troyes (partie étudiante)

Statut : version 1, sprint 0
Périmètre audité : compte étudiant, département MMI, semestre 5
Version de l'application observée : 3.19.68

---

## 1. Méthode et limites

Audit exploratoire mené sur un compte étudiant réel, sur les écrans accessibles sans droit
d'administration : tableau de bord, documents, recherche globale. Trois postures de test :
navigation desktop, navigation mobile émulée (375 x 812), et inspection du DOM pour les mesures
de contraste et la structure sémantique.

Ce que cet audit ne couvre pas encore, et qui doit l'être avant la fin du sprint 1 :

- les écrans Agenda, Applications, Trombinoscope et Messagerie,
- les parcours réels chronométrés auprès d'étudiants (tri par cartes et test d'arborescence),
- un audit RGAA formel avec un lecteur d'écran.

Les mesures de contraste sont calculées selon la formule WCAG 2.x sur les couleurs effectivement
appliquées par le navigateur.

---

## 2. Socle technique

| Élément | Observation |
|---|---|
| Backend | Symfony 6.x, rendu serveur Twig |
| Assets | Webpack Encore, chunks versionnés sous `/build/` |
| Routing exposé au JS | FOSJsRoutingBundle, 167 routes publiées |
| CSS | Bootstrap 5, thème administrateur |
| Police | Roboto |
| Icônes | police à ligatures (Material) |
| Multi-établissement | classe CSS de site sur `<body>` (`troyes`) |
| Source du code | dépôt public `Dannebicque/intranetV3` |
| Sources de données | Celcat pour les emplois du temps, Apogée pour la scolarité |

Conséquence directe : le modèle de données, les contrôleurs et les gabarits sont lisibles
publiquement. L'équipe n'a pas besoin d'attendre une livraison du client pour cartographier le
domaine.

### Domaine métier déduit du routing

`absences`, `notes`, `evaluation`, `edt`, `matiere`, `groupe`, `etudiant`, `personnel`,
`stage`, `alternance`, `apc` (approche par compétences BUT), `trombinoscope`, `messagerie`,
`actualite`, `reservation`, `borne`, `agenda`, `recherche`.

### Jetons de direction artistique relevés

| Rôle | Valeur |
|---|---|
| Primaire (orange) | `#F7B000` |
| Texte sur primaire | `#4D3677` (violet foncé) |
| Texte courant | `#4D5259` |
| Fond d'application | `#F5F6FA` |
| Succès | `#15C377` |
| Danger | `#F96868` |
| Typographie | Roboto, corps 13 à 15 px, graisse 300 à 400 |

Un mode sombre existe déjà dans le menu utilisateur : à prendre en compte dès la conception du
design system, pas après.

---

## 3. Architecture de l'information

La navigation principale compte cinq entrées : Dashboard, Trombinoscope, Agenda, Applications,
Documents. Un menu utilisateur secondaire ajoute Messagerie, Profil, Paramètres, mode sombre.

Deux problèmes de fond :

**Le vocabulaire est celui de l'institution, pas celui de l'étudiant.** « Trombinoscope »,
« Applications », « Modalités de Contrôle des Connaissances » décrivent des objets
administratifs. Un étudiant cherche un cours, une salle, une note, une personne à contacter.

**Rien ne porte la notion de matière ou de SAE**, alors que c'est l'unité dans laquelle
l'étudiant organise mentalement toute sa scolarité. Les documents, les notes et l'emploi du
temps vivent dans trois silos séparés qui ne se rejoignent jamais.

---

## 4. Constats par écran

### 4.1 Tableau de bord

Le tableau de bord empile, dans l'ordre : la grille d'emploi du temps de la semaine entière, un
tableau d'absences, un tableau de notes, le tableau des modalités de contrôle des connaissances
(14 lignes), une liste de liens utiles, puis une liste de contacts.

| # | Constat | Gravité |
|---|---|---|
| TB-1 | Aucune hiérarchie : la grille hebdomadaire complète occupe le premier écran alors que l'information la plus demandée est « mon prochain cours, à quelle heure, dans quelle salle ». | Élevée |
| TB-2 | Le cours en cours et le jour courant ne sont pas mis en évidence dans la grille. | Élevée |
| TB-3 | Les tableaux vides sont rendus intégralement, en-têtes compris, avec une ligne « Aucune note n'a été saisie ». Deux blocs occupent l'écran pour ne rien dire. | Moyenne |
| TB-4 | Le tableau des modalités de contrôle affiche des compétences suivies de nombres entre parenthèses, sans légende. L'information n'est pas interprétable. | Moyenne |
| TB-5 | Le bloc Contacts répète six fois les deux mêmes personnes, une fois par parcours du BUT. | Moyenne |
| TB-6 | Un avertissement de quatre lignes sur la synchronisation Celcat est placé au-dessus de l'emploi du temps, à chaque visite, sans possibilité de le masquer. | Faible |
| TB-7 | Un bouton flottant orange « accueil » se superpose au contenu en haut à droite, sans libellé. | Faible |

### 4.2 Documents

Neuf cartes de catégories : Vos documents favoris, Alternance, Cellule handicap, Demonstration
Intranet, Documents officiels, Plannings, Relations internationales, Stages informations,
Stages offres.

| # | Constat | Gravité |
|---|---|---|
| DOC-1 | Les catégories reflètent l'organigramme de l'établissement, pas la vie de l'étudiant. Aucune entrée « cours », « matière », « SAE ». Un étudiant qui cherche un support de cours n'a aucune piste. | Critique |
| DOC-2 | Les cartes de catégorie sont des `<div>` sans lien, sans `tabindex` et sans rôle. Elles ne sont ni atteignables au clavier, ni annoncées comme cliquables, et n'ont pas d'URL propre : une catégorie ne peut pas être mise en favori ni partagée. | Critique |
| DOC-3 | Le compteur de documents affiche le libellé « Nb. de documents dans la catégorie » sans la valeur sur huit cartes sur neuf. Aucune indication de volume, de fraîcheur ou de contenu. | Élevée |
| DOC-4 | Pas de recherche interne, pas de filtre, pas de tri, pas de vue « récents », pas de vue « nouveautés ». La seule stratégie possible est l'exploration séquentielle. | Élevée |
| DOC-5 | Hiérarchie de titres incohérente : `h1` suivi directement de neuf `h5`, sans `h2`. | Moyenne |
| DOC-6 | À 800 px de large, une seule carte par ligne, hauteur d'environ 180 px pour trois mots utiles. Neuf catégories demandent plusieurs écrans de défilement. | Moyenne |

### 4.3 Recherche globale

Déclenchée par l'icône loupe ou par le raccourci annoncé `cmd+k`. Appelle
`GET /fr/recherche/?q=...`, répond en 50 à 90 ms.

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

### 4.4 Mobile (375 x 812)

| # | Constat | Gravité |
|---|---|---|
| MOB-1 | En vue jour, le bloc de cours est positionné dans une colonne décalée, sans axe horaire ni en-tête de jour visibles, avec une large zone vide à sa gauche. L'information devient difficile à situer. | Élevée |
| MOB-2 | Deux barres de navigation temporelle empilées, aux libellés et aux styles incohérents (« Semaine » puis « Aujourd'hui »). | Moyenne |
| MOB-3 | Le libellé du bouton « Déposer un justificatif » déborde de son conteneur. | Moyenne |
| MOB-4 | 26 éléments interactifs sur 66 mesurent moins de 44 px dans au moins une dimension, en dessous de la cible tactile recommandée. | Moyenne |
| MOB-5 | L'avertissement Celcat occupe quatre lignes avant tout contenu utile. | Faible |

Point positif : pas de débordement horizontal, et la balise `viewport` est correctement
configurée.

### 4.5 Accessibilité transverse

| # | Constat | Mesure | Gravité |
|---|---|---|---|
| A11Y-1 | Les libellés de navigation contiennent la ligature de l'icône, non masquée aux technologies d'assistance. Un lecteur d'écran annonce « dashboard Dashboard », « group Trombinoscope », « calendar Agenda ». | — | Élevée |
| A11Y-2 | Boutons verts : texte blanc sur `#15C377`. | 2,31:1 (minimum requis 4,5:1) | Élevée |
| A11Y-3 | Boutons rouges : texte blanc sur `#F96868`. | 2,91:1 | Élevée |
| A11Y-4 | Blocs de cours de l'emploi du temps : texte blanc sur fond vert, même famille chromatique. À mesurer précisément. | à confirmer | Élevée |
| A11Y-5 | Aucun lien d'évitement vers le contenu principal. | — | Moyenne |
| A11Y-6 | Trois éléments `<header>` sur une même page, hiérarchie de titres discontinue. | — | Moyenne |

À l'inverse, et il faut le dire : le texte courant `#4D5259` sur blanc atteint **7,87:1**, et le
texte violet foncé sur le bouton orange primaire atteint **5,29:1**. Les deux passent le niveau
AA confortablement. Le choix de ne pas mettre de blanc sur l'orange est une décision juste, qui
n'a simplement pas été appliquée aux couleurs vert et rouge. La palette n'est pas à refaire, elle
est à compléter et à normaliser.

---

## 5. Ce qui fonctionne et doit être conservé

- La palette et l'identité visuelle sont cohérentes et reconnaissables.
- Le contraste du texte courant est excellent.
- La recherche existe déjà, est rapide (50 à 90 ms) et dispose d'un raccourci clavier annoncé.
- Un mode sombre est déjà prévu.
- L'application est déjà responsive dans sa structure, sans débordement horizontal.
- Le domaine métier est riche et complet côté données.

Le problème n'est pas la qualité du socle. C'est que l'interface expose la structure de la base
de données plutôt que les tâches de l'étudiant.

---

## 6. Opportunités, classées

| Priorité | Opportunité | S'appuie sur |
|---|---|---|
| 1 | Recherche universelle tolérante aux fautes, multi-types, entièrement accessible au clavier | RECH-1 à RECH-9 |
| 2 | Réorganisation documentaire par matière et par SAE, avec facettes, récents et épinglés | DOC-1 à DOC-6 |
| 3 | Tableau de bord recentré sur « maintenant » : prochain cours, salle, échéances, absences à justifier | TB-1 à TB-7 |
| 4 | Vue emploi du temps mobile repensée | MOB-1 à MOB-5 |
| 5 | Normalisation du design system, contrastes compris, en conservant la direction artistique | A11Y-2 à A11Y-6 |

Les deux premières correspondent exactement à la demande initiale du client. Les trois suivantes
en découlent naturellement et ne nécessitent pas d'élargir le périmètre.

---

## 7. Questions ouvertes à la suite de l'audit

1. Les compteurs de documents sont-ils vides parce qu'il n'y a réellement aucun document dans ces
   catégories, ou s'agit-il d'un défaut d'affichage ?
2. Existe-t-il, dans le modèle de données, un lien entre un document et une matière ou une SAE, ou
   la catégorie est-elle le seul axe de classement disponible ?
3. La recherche est-elle volontairement limitée à trois types, ou est-ce un état intermédiaire ?
4. Jusqu'où la direction artistique peut-elle évoluer : couleurs et logo conservés mais mise en
   page libre, ou iso-visuel strict ?
5. Le mode sombre doit-il être couvert par notre partie étudiante ?
6. Des comptes de test sont visibles dans les résultats de recherche en production : doit-on les
   considérer comme des données réelles dans nos jeux d'essai ?

---

## 8. Annexe — points d'entrée observés

| Usage | Route |
|---|---|
| Recherche globale | `GET /fr/recherche/?q={terme}` |
| Tableau de bord | `GET /fr/tableau-de-bord` |
| Documents | `GET /fr/document` |
| Agenda, vue semaine | `GET /fr/agenda/{semaine}` |
| Agenda, vue étudiant | `GET /fr/agenda/{semaine}/etudiant` |
| Trombinoscope | `GET /fr/trombinoscope/` |
| Applications | `GET /fr/application` |

Ces routes renvoient du HTML rendu côté serveur. Elles documentent le périmètre fonctionnel, pas
un contrat d'API : le contrat reste à rédiger et à faire valider par le client.
