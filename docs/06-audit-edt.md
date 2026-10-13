# Audit de l'emploi du temps étudiant

Statut : 13 octobre 2026, `develop` au commit `3b54910f` (#143).
Périmètre : la page « Emploi du temps » de l'étudiant (`/intranet/agenda`), son API et ses données.
Objectif : en faire la fonctionnalité de référence de l'application, belle, lisible, juste pour
toute durée de cours (45 min, 1 h, 1 h 15, 1 h 30, 2 h, 3 h…), aussi bonne en mobile qu'en bureau.

La numérotation continue celle de l'audit 05 : EDT-7 suit EDT-6, MOB-11 suit MOB-10. Gravité sur
la même échelle : Critique, Élevée, Moyenne, Faible.

---

## 1. Méthode

- Compte `etudiant`, semaine 42 (12 au 16 octobre 2026), en bureau (1 440 x 900), en mobile
  émulé (375 x 812) et en mode sombre.
- Mesures par script dans la page : hauteurs de grille et de cartes, contenu caché, contrastes
  calculés après conversion par un `canvas`, ordre de tabulation, requêtes réseau.
- Les données de test n'ayant que des cours de 2 h, des cours de 45 min, 1 h, 1 h 15, 1 h 30 et
  deux cours qui se chevauchent ont été **injectés dans la page**, sans rien écrire en base.
- API interrogée directement, avec et sans connexion.
- Lecture du code : `EdtEtudiant.vue`, `EdtEvent.vue`, `utils/agenda.js` ; le back
  (`EdtEvent`, synchronisation Celcat, fausse base) relu par Antigravity, les sources de vue-cal
  5.0.1-rc.46 par OpenCode. Chacun de leurs constats repris ici a été vérifié dans le code.

## 2. Ce qui marche, à garder

- Chaque cours est un bouton atteignable au clavier, avec un nom complet (« R1.11 - Développement
  web, CM, mardi 13 octobre de 8 h 00 à 10 h 00, Amphithéâtre ») ; le détail rend le focus en se
  fermant (C14).
- Le changement de jour ou de semaine est annoncé aux lecteurs d'écran.
- Titre de période bien écrit (« 12 – 16 octobre 2026 ») et double numérotation des semaines
  expliquée sur une ligne (EDT-4 corrigé).
- Boutons nommés, de 44 px ; vue jour par défaut sur téléphone (C3) ; actions enseignant retirées
  (C15) ; ligne de l'heure courante ; textes des cartes à 19:1.
- Tests : 4 parcours Cypress (`agenda-etudiant.cy.js`), 12 tests unitaires (`agenda.test.js`).

## 3. Constats

### Lisibilité et adaptabilité aux durées

| # | Constat | Gravité |
|---|---|---|
| EDT-7 | Grille fixe de 8 h à 21 h, au pas de 30 min, 40 px par demi-heure : 1 040 px de haut. En 1 440 x 900, seule la matinée est visible ; les cours finissent à 17 h au plus tard, chaque soirée ajoute 4 h de cases vides. | Élevée |
| EDT-8 | Un cours court perd ses informations. La carte demande environ 150 px ; un cours de 1 h (80 px) coupe son horaire, un cours de 45 min (60 px) perd son horaire et son enseignant. Le reste défile *dans* la carte (`overflow: scroll`, barre masquée) : rien ne signale qu'il manque quelque chose. Toute formation à cours de 1 h ou moins est touchée. | Critique |
| EDT-9 | Deux cours qui se chevauchent : `stack-events` élargit chaque carte de 15 % et les superpose. À 106 et 138 px de large, titre et enseignant passent sur trois lignes et débordent ; le titre est réduit au code du module. | Élevée |
| EDT-10 | Couleurs en dur et saturées, par type de groupe : `#33C1FF` (CM), `#ffee33` (TD), `#33FF57` (TP), gris sinon. Hors des jetons du preset, ce que `CLAUDE.md` interdit. Pâlies par `adjustColor`, puis rendues transparentes à 90 % : la grille se voit sous le texte. Une SAÉ, une évaluation ou une réunion tombe dans le gris. | Moyenne |
| EDT-11 | Bordure de carte à 1,72:1 sur le fond blanc, fond de carte à 1,07:1 : une carte de cours, élément d'interface, n'atteint pas 3:1. | Élevée |
| EDT-12 | Bordures et défilement des cartes posés à la main dans le DOM après chaque chargement (`querySelectorAll('.vuecal__event')`). Un cours rendu plus tard n'a pas de bordure (vérifié en injectant des cours). La ligne `cssText += '::-webkit-scrollbar…'` est sans effet. | Moyenne |
| EDT-13 | Redondances : « CM CM » (le type, puis le libellé du groupe CM) ; l'enseignant écrit « Claire MARTIN » sur la carte et « Martin Claire » dans le détail. | Faible |
| EDT-14 | Mode sombre : les cartes restent pâles, texte noir, sur une grille sombre ; le calendrier prend un fond bleu ardoise étranger aux jetons sombres de `CLAUDE.md` (`#1B1D21`, `#25282D`). | Moyenne |
| EDT-15 | En bureau, la vue Jour étire un cours sur 1 100 px de large. La vue choisie n'est pas retenue ; la vue de départ ne dépend que de la largeur au chargement. | Faible |
| EDT-16 | Le samedi est masqué d'office (`hide-weekends`) : un cours planifié un samedi dans Celcat serait invisible, sans avertissement. | Moyenne |
| EDT-17 | Ligne de l'heure courante en rouge `rgba(255, 0, 0, .6)`, en dur ; son libellé (« 08:52 ») se pose sur le texte du cours en cours. Aucun « En cours » ni « Dans 20 min » sur la carte. | Faible |
| EDT-18 | Détail d'un cours ouvert à la souris : pas modal (`aria-modal` absent, pas de masque), focus laissé sur la page, largeur fixe de 25 % de l'écran. Aucun lien vers la matière, ses documents ou ses autres séances. | Moyenne |

### Mobile

| # | Constat | Gravité |
|---|---|---|
| MOB-11 | Avant le premier cours, 45 % de l'écran : fil d'Ariane, titre de 48 px, description, date, numéros de semaine sur deux lignes, bascule Jour / Semaine et bouton « Aujourd'hui ». | Élevée |
| MOB-12 | La journée est une grille horaire d'une colonne : une journée à un cours montre un cours puis onze heures de cases vides. Ni liste, ni bandeau des jours de la semaine, ni balayage : on change de jour par deux flèches. | Élevée |
| MOB-13 | EDT-8 y est plus grave : la carte, pleine largeur, garde la même hauteur par minute ; un cours de 1 h ne montre ni son horaire ni son enseignant. | Élevée |

### Données et API

| # | Constat | Gravité |
|---|---|---|
| EDT-19 | `GET /api/edt_events` et `/api/edt_events/{id}` répondent **sans connexion** : les 293 créneaux de tous les groupes, avec salle et enseignant (289 nommés). Même famille que E22 à E24. | Critique |
| EDT-20 | Les heures de Paris sont envoyées marquées UTC (`2026-09-04T10:15:00+00:00`) ; le front les recale avec `getTimezoneOffset`. Tout autre client de l'API lit un horaire faux d'une ou deux heures. | Moyenne |
| EDT-21 | Chaque changement de jour ou de semaine enchaîne trois requêtes l'une après l'autre (semaine de formation, groupes de l'étudiant, créneaux), même quand la semaine est déjà chargée : environ 330 ms en local, davantage en production. Aucun indicateur de chargement, aucun cache. | Moyenne |
| EDT-22 | Celcat ne fournit ni couleur, ni évaluation, ni annulation : `couleur` est toujours vide et `evaluation` toujours faux, donc le badge « éval. » ne s'affiche jamais. La date de modification Celcat (`updatedEvent`) est stockée mais pas exposée. | Moyenne |
| EDT-23 | La fausse base Celcat n'a que des cours de 2 h, deux de 4 h 15 et une réunion d'1 h, entre 8 h et 17 h : ni 45 min, ni 1 h 15, ni 1 h 30, ni 3 h, aucun chevauchement pour l'étudiant de test, aucun samedi. EDT-8, EDT-9 et EDT-16 ne se voient pas sans les simuler. | Élevée |

### vue-cal 5.0.1-rc.46

Version candidate, pas une version stable. Ce qu'elle offre : pas et hauteur de grille réglables
(`timeStep`, `timeCellHeight`), plage horaire, chevauchements calculés côte à côte, emplacements
pour la carte, l'en-tête de jour et la ligne de l'heure, défilement à une heure (`scrollToTime`).
Ce qu'elle n'a pas : **aucun attribut ARIA, rôle ni gestion clavier**, aucune vue liste, aucune
bascule mobile (un seul point de rupture, à 500 px, pour des marges). `timeFormat` est déclaré
mais jamais lu.

---

## 4. La cible

Huit principes, qui servent de critères d'acceptation aux lots de la section 5.

1. **Proportionnel et exact.** Une minute vaut toujours la même hauteur : toute durée tombe juste,
   au quart d'heure près. Graduation à l'heure, trait léger à la demi-heure. La plage horaire
   est propre à l'étudiant et **ne bouge jamais d'une semaine à l'autre** : de l'heure pleine
   avant son cours le plus matinal de l'année à l'heure pleine après son cours le plus tardif.
   Un étudiant qui ne commence jamais avant 8 h et ne finit jamais après 19 h voit 8 h – 19 h.
   Sans cours connu, 8 h – 20 h, comme l'intranet en ligne.
2. **La carte s'adapte à sa hauteur.** Trois densités : complète (intitulé, type, groupe, salle,
   enseignant, horaire), compacte (code, salle, horaire), une ligne (« R1.06 · C110 · 13 h 30 »).
   Jamais de défilement dans une carte ; le détail complet reste à un clic et dans le nom
   accessible.
3. **La couleur dit le type, le texte aussi.** CM, TD, TP, SAÉ, évaluation, autre : une palette de
   jetons du preset, en clair et en sombre, chaque paire mesurée. Un liseré épais à 3:1 délimite
   la carte. Le type reste écrit : la couleur ne porte jamais seule l'information.
4. **Le mobile est pensé pour le mobile.** Bandeau des cinq jours (date et nombre de cours),
   journée en liste chronologique avec ses pauses (« Pause de 1 h 15 »), balayage d'un jour à
   l'autre, en-tête compact. En mobile, la semaine est une liste groupée par jour.
5. **L'instant d'abord.** Aujourd'hui marqué, ligne de l'heure aux couleurs du preset, « En cours »
   et « Dans 20 min » sur la carte. Le week-end, ouverture sur le prochain jour de cours.
6. **Rapide et sûr.** Une route `/api/me/…` qui rend les créneaux de l'étudiant pour une période,
   heures avec leur fuseau ; cache par semaine et préchargement de la suivante ; squelette de
   chargement ; état vide expliqué (vacances, journée sans cours) ; erreur avec « Réessayer ».
7. **Une adresse pour chaque vue.** Date et vue dans l'URL (`?date=2026-10-14&vue=semaine`) :
   le retour arrière, le rechargement et le partage tombent au même endroit. La vue choisie est
   retenue.
8. **Accessible, vérifié.** Flèches du clavier pour changer de jour, annonce de la période,
   détail modal qui prend et rend le focus, cibles de 44 px, contrastes mesurés par Cypress en
   clair et en sombre.

## 5. Plan

Fiche C17 du backlog. Un lot, une branche, une PR. Les agents travaillent dans des worktrees
jetables ; LCS conçoit, relit chaque diff et ouvre les PR.

| Lot | Contenu | Corrige | Taille | Qui |
|---|---|---|---|---|
| C17.0 | **Données de test** : fausse base Celcat enrichie (45 min, 1 h, 1 h 15, 1 h 30, 3 h, deux cours qui se chevauchent dans un groupe de l'étudiant, un cours à 7 h 45 hors de ses groupes et un jusqu'à 19 h, un samedi ; les semaines de vacances sans cours existent déjà). Les tests existants restent verts. | EDT-23 | S | OpenCode (lignes ajoutées sur le modèle existant), LCS |
| C17.1 | **[back] API de l'étudiant** : `/api/me/emploi-du-temps?semaine=AAAA-MM-JJ`, une semaine par appel pour la mettre en cache (ses groupes, semaine de formation, heures avec fuseau, plage horaire de son année) ; `/api/edt_events` fermée aux anonymes, inchangée pour le personnel. PHPUnit. | EDT-19, EDT-20, EDT-21 | M | LCS, Antigravity en relecture |
| C17.2 | **Fondations** : jetons de type de séance dans le preset (clair et sombre, mesurés) ; fonctions pures dans `utils/agenda.js` : plage horaire, densité d'une carte, pauses, regroupement par jour. Vitest. | EDT-10, EDT-14 | M | LCS ; OpenCode écrit la série de tests |
| C17.3 | **Semaine en bureau** : carte de cours propre à l'étudiant (sans toucher `EdtEvent.vue`, partagé avec le personnel), plage horaire de l'étudiant, échelle au quart d'heure, chevauchements côte à côte, mode sombre, plus aucun style posé dans le DOM, samedi ajouté la semaine où il a un cours. | EDT-7 à EDT-9, EDT-11 à EDT-17 | M | LCS |
| C17.4 | **Mobile** : bandeau des jours, journée en liste avec ses pauses, balayage, en-tête compact, semaine en liste. | MOB-11 à MOB-13 | L | LCS ; Antigravity relit clavier et RGAA |
| C17.5 | **Navigation et états** : date et vue dans l'URL, vue retenue, cache et préchargement, squelette, état vide, erreur avec « Réessayer ». | EDT-15, EDT-21 | M | LCS ; OpenCode pour les tests |
| C17.6 | **Détail d'un cours** : modal accessible, feuille du bas en mobile, noms cohérents, liens vers la matière (après P1) et ses documents. | EDT-13, EDT-18 | S | LCS |
| C17.7 | **Cohérence** : widgets « Maintenant » et « Aujourd'hui » de l'accueil sur la même carte et les mêmes jetons. | — | S | OpenCode, LCS |

Ordre : C17.0, C17.1 et C17.2 en parallèle (fichiers distincts), puis C17.3, C17.4, C17.5, C17.6
et C17.7. Chaque lot finit par un parcours Cypress en bureau et en mobile, sur une base neuve.

Plus tard, hors de C17 : abonnement iCal par jeton aléatoire (celui de la V3, `MD5(prenom.nom)`,
est une faille) ; cours modifiés et annulés, si Celcat fournit l'information (EDT-22).

## 6. Décisions

Prises par LCS le 13/10/2026 :

- **Plage horaire** : propre à chaque étudiant, calculée sur toute son année, jamais d'une semaine
  à l'autre : une grille qui change de hauteur chaque lundi perturbe. 8 h – 20 h par défaut, comme
  l'intranet en ligne (principe 1).
- **Samedi** : masqué, ajouté la semaine où l'étudiant y a un cours.
- **Coordination** : JEREMY, titulaire de la colonne C et auteur de C3, C14 et C15 sur
  `EdtEtudiant.vue`, est d'accord.

Retenues par défaut, à revoir si l'équipe s'y oppose :

1. **Couleur par type de séance**, pas par matière. Six couleurs au plus, stables d'une semaine à
   l'autre, chacune mesurable en clair et en sombre ; c'est l'usage de Celcat. La matière reste
   lisible par son code, en gras.
2. **vue-cal gardé** pour la grille de la semaine en bureau (C17.3) ; la vue mobile, que vue-cal
   n'offre pas, est écrite par nous. Le remplacer par notre propre grille reste possible après
   C17.4, si son absence d'accessibilité nous coûte trop.
