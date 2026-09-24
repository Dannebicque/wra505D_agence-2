# Audit de l'existant, version 2 — uniServices (partie étudiante)

Statut : version 2, 24 septembre 2026. Remplace l'audit 01 pour tout ce qui touche à l'interface.
Périmètre audité : compte de test `etudiant` (Jane Doe), département MMI, semestre 1
Version observée : uniServices 1.0.0, branche `develop` au commit `949615a8`, PR #31 comprise

L'audit 01 portait sur l'intranet V3 en production. L'interface étudiante a depuis été remplacée
par celle d'uniServices, reprise dans `uniservices/`. Cet audit reprend la méthode et la
numérotation du premier : les familles de constats sont conservées et leur numérotation continue
(TB-8 suit TB-7). Un identifiant désigne donc toujours un seul constat. La section 6 indique ce
que sont devenus les constats de l'audit 01.

---

## 1. Méthode et limites

Audit mené en local sur le compte `etudiant`, écran par écran : connexion, portail, tableau de
bord de l'intranet, emploi du temps, scolarité, cahier de texte, profil, documents,
trombinoscope, recherche, barre haute. Quatre postures :

- navigation en bureau (1 280 x 800) et en mobile émulé (375 x 812) ;
- inspection du DOM par script : contraste, titres, repères, noms accessibles, champs sans
  étiquette, éléments cliquables non focalisables, cibles tactiles, débordements ;
- parcours réel à la touche Tab, avec contrôle visuel du focus ;
- interrogation directe de l'API, pour distinguer un défaut d'affichage d'une donnée absente.

Les contrastes sont calculés selon la formule WCAG 2.x sur les couleurs effectivement rendues. Le
fond est recomposé en remontant les ancêtres, opacités comprises. Le texte posé sur une image et
le texte réservé aux lecteurs d'écran sont exclus. Seuils : 4,5:1 pour le texte courant, 3:1 pour
le grand texte.

Ce que cet audit ne couvre pas :

- **Données.** Ce sont les fixtures et la fausse base Celcat : noms fictifs, documents
  d'exemple tous datés du même jour. Les intervenants de l'emploi du temps sont inconnus de
  l'annuaire. Aucun de ces points n'est compté comme défaut d'interface.
- **Performances.** Le serveur de développement Vite ne donne pas de chiffres représentatifs.
- **Validation humaine.** Pas de lecteur d'écran, pas de test avec des étudiants, pas d'audit
  RGAA formel.
- **Modules non activés pour MMI**, hors documents et trombinoscope : stages, helpdesk,
  questionnaires, UniFolio.

---

## 2. Ce qui a changé depuis l'audit 01

| Élément | Intranet V3 (audit 01) | uniServices (aujourd'hui) |
|---|---|---|
| Serveur | Symfony 6, rendu Twig | Symfony 7 et API Platform, réponses JSON-LD (Hydra) |
| Interface | pages serveur, Bootstrap 5 | application Vue 3 et Vite, PrimeVue 4 (thème Aura) et Tailwind 4 |
| Organisation | une application | un portail, puis des modules : UniTranet, Documents, HelpDesk, Questionnaires, Stages, UniFolio, Correcto |
| Navigation étudiante | 5 entrées | portail, puis 4 entrées dans l'intranet : Dashboard, Agenda, Scolarité, Cahier de texte |
| Couleur primaire | `#F7B000`, commune | une couleur par module (section 5) |
| Police | Roboto | Roboto, servie en local depuis #31 |
| Icônes | police à ligatures (Material) | PrimeIcons (CSS), et des emoji dans le module Documents |
| Emploi du temps | grille serveur | calendrier vue-cal, alimenté par le connecteur Celcat |
| Recherche | `/fr/recherche`, 3 types, sans tolérance | `GET /api/recherche`, 5 types, tolérante aux fautes, **sans aucun écran** |
| Mode sombre | présent | présent, lisible |

---

## 3. Architecture de l'information

**Deux pages d'accueil.** Après connexion, l'étudiant arrive sur le portail (« Portail - MMI »),
qui liste les applications et affiche des widgets. Le module intranet a son propre tableau de
bord (« Dashboard »), qui ouvre sur le même bloc de salutation et le même widget « Aujourd'hui ».
Rien n'indique lequel est la page de référence.

**Le vocabulaire est devenu celui du code.** Le portail et le carrousel de la page de connexion
nomment les modules par leur nom technique : « DocumentBundle », « QuestionnaireBundle »,
« StageBundle ». C'est un recul par rapport à l'audit 01, qui reprochait déjà un vocabulaire
d'institution.

**Ce qui est affiché ne correspond pas à ce qui est ouvert.**
- Le module Documents est classé « Non activé » sur le portail, mais `/app/documents` s'ouvre et
  liste 30 documents.
- Le trombinoscope n'apparaît pas dans le menu étudiant, mais s'ouvre par son adresse.

**La matière et la SAE ne structurent toujours rien.** Les documents sont classés par service :
Ressources humaines, Finance et comptabilité, Technique et documentation. Seul le moteur de
recherche connaît les matières (fiche D2), et aucun écran ne l'interroge.

---

## 4. Constats par écran

Gravité : Critique, Élevée, Moyenne, Faible, sur la même échelle que l'audit 01.

### 4.1 Connexion

| # | Constat | Gravité |
|---|---|---|
| CNX-1 | Le logo de l'IUT ne se charge pas : son texte alternatif « logo de l'iut » s'affiche à la place de l'image. Les logos des applications du carrousel portent tous ce même texte alternatif. | Moyenne |
| CNX-2 | Le carrousel des applications est le premier élément atteint au clavier. Il affiche les noms techniques des modules, et ses boutons de pagination ont pour seul nom « < » et « > ». | Moyenne |
| CNX-3 | « Se souvenir de moi » : l'étiquette vise `rememberme1`, mais la case n'a pas d'`id`. Cliquer sur le libellé ne coche pas la case, et la case n'a pas de nom accessible. | Moyenne |
| CNX-4 | « Connexion URCA » en blanc sur jaune `#EAB308` : 1,92:1. Le lien « Mot de passe oublié ? », jaune sur blanc, fait aussi 1,92:1. | Élevée |

Point positif : un identifiant erroné déclenche un message en `role="alert"`, donc annoncé, et
les champs Login et Mot de passe sont bien étiquetés.

### 4.2 Portail

| # | Constat | Gravité |
|---|---|---|
| POR-1 | Une seule application est ouverte. Six autres sont listées sous « Non activé », grisées, non cliquables et sous leur nom de code. En mobile, cette liste occupe tout le premier écran avant le moindre contenu. | Élevée |
| POR-2 | Contenu factice livré. Les trois boutons « Accéder » des liens utiles n'ont ni destination ni action, et portent le même libellé. Le bloc Contacts affiche deux fois « JOHN DOE », écrit en dur, et son bouton « Voir plus » ne fait rien. | Élevée |
| POR-3 | Le bouton « Retour », présent en tête de chaque page, revient en arrière dans l'historique (`router.go(-1)`). Sur le portail, page d'arrivée, il renvoie vers l'écran de connexion. Il double le bouton du navigateur et le fil d'Ariane. | Faible |

### 4.3 Tableau de bord de l'intranet

| # | Constat | Gravité |
|---|---|---|
| TB-8 | **Information fausse.** Le widget « Aujourd'hui » annonce « Aucun événement aujourd'hui », alors que l'emploi du temps contient ce jour-là un cours : SAE1.01, de 8 h à 12 h 15, salle B204. L'API `edt_events?day=2026-09-24` renvoie 0 créneau, alors que `edt_events?semaineFormation=3` renvoie bien celui-ci. Même défaut sur le portail. | Critique |
| TB-9 | Deux widgets disent la même chose en deux formulations : « Maintenant : Plus de cours aujourd'hui » et « Aujourd'hui : Aucun événement aujourd'hui ». Le premier est juste, le second non (TB-8). | Moyenne |
| TB-10 | Le widget « Notes » est un pense-bête. Ses données (« Relancer alternants absents », « Préparer réunion pédagogique ») visent le personnel. Il s'affiche en barres grises vides suivies de « Date limite : ». Pour un étudiant, « Notes » désigne ses notes d'examen. | Élevée |

Points positifs : le widget « Maintenant » (fiche C1) est en tête et dit juste. La fiche C5
supposait que l'API refuse la personnalisation à un étudiant : `GET /api/widgets/available/intranet`
répond désormais 200. Elle est à revérifier avant d'être traitée.

### 4.4 Emploi du temps

| # | Constat | Gravité |
|---|---|---|
| EDT-1 | Les blocs de cours sont des `div` sans `tabindex` ni rôle. Leur détail (groupe, effectif, semestre) ne s'ouvre qu'à la souris : absent de l'ordre de tabulation. | Élevée |
| EDT-2 | La fenêtre de détail d'un cours propose à l'étudiant les actions enseignant « Appel » et « Tous présents ». Ces boutons n'ont aucun effet, et mesurent 28 x 28 px. | Élevée |
| EDT-3 | Les flèches « semaine précédente » et « semaine suivante » sont des boutons icônes sans nom accessible. | Élevée |
| EDT-4 | Deux numérotations de semaine sont affichées côte à côte, sans explication : « Semaine 39 » et « Semaine de formation : 3 ». | Faible |
| EDT-5 | Le sous-titre promet « celui du département et les statistiques ». L'étudiant n'a que l'onglet « Personnel ». | Faible |
| EDT-6 | Les intervenants affichés (Martin Claire, Roussel Inès…) viennent de Celcat, pas de l'annuaire : la recherche ne les trouve pas. C'est déjà la réserve « Reste » de la fiche C9. | Moyenne |

Points positifs :
- La vue jour a un axe horaire et un bloc pleine largeur.
- Une ligne marque l'heure courante.
- La fenêtre de détail a `role="dialog"`, un titre relié par `aria-labelledby`, et le focus s'y
  déplace. Il manque `aria-modal`.

### 4.5 Scolarité, cahier de texte, profil

| # | Constat | Gravité |
|---|---|---|
| SCO-1 | La page Scolarité est un gabarit inachevé : deux cartes « Semestre : OK », texte écrit en dur dans `ScolariteView.vue`, corps vide. L'étudiant n'a accès, dans l'interface, à aucune note, absence ni moyenne. | Critique |
| SCO-2 | Le cahier de texte reprend le même gabarit : « Suivi des rendus / Semestre / OK ». | Élevée |
| SCO-3 | Le profil affiche vides le prénom, le nom, le numéro étudiant, l'INE, le login et l'adresse universitaire. L'API `/api/etudiants/{id}` renvoie pourtant le prénom, le nom, le login et l'adresse. La date de naissance s'affiche « Non spécifiée (Âge inconnu ans) ». La page compte trois `h1`. | Élevée |

### 4.6 Documents

Module classé « Non activé » pour MMI, mais accessible (section 3).

| # | Constat | Gravité |
|---|---|---|
| DOC-7 | 80 éléments cliquables, cartes de document et étiquettes, sont des `div` non focalisables : aucune fiche ne s'ouvre au clavier. | Critique |
| DOC-8 | « Voir détails » et « Télécharger » sont dans une rangée d'opacité 0, révélée au survol. Au clavier, « Télécharger » reçoit le focus en restant invisible. | Élevée |
| DOC-9 | À 1 280 px, les titres de documents sont tronqués à 4 ou 5 caractères (« Analy… », « Procé… »). Le libellé du champ de recherche est lui aussi coupé. | Élevée |
| DOC-10 | La recherche du module est exacte, sans tolérance : « guide » renvoie 6 documents, « gide » aucun. `/api/recherche?q=gide` en trouve pourtant 10. Le nombre de résultats n'est pas annoncé (aucune région `aria-live`). | Élevée |
| DOC-11 | Des emoji servent d'icônes (types de fichier, auteur, date, téléchargement, favori, catégories). Les lecteurs d'écran les lisent (« graphique à barres », « silhouette en buste »…), et la règle du projet les proscrit de l'interface. | Moyenne |
| DOC-12 | La page n'a pas de `h1` : elle commence par un `h3` « Catégories », puis un `h2` « Tous les documents ». | Moyenne |
| DOC-13 | Les catégories sont des boutons atteignables au clavier, mais sans adresse propre : l'URL ne change pas, une catégorie ne se partage pas et ne se met pas en favori. Leurs chevrons de dépliage n'ont pas de nom accessible. | Moyenne |

### 4.7 Trombinoscope

| # | Constat | Gravité |
|---|---|---|
| TROMBI-1 | Chaque fiche affiche un objet JSON brut de l'API (`{ "@id": "/api/etudiant_scolarites/24", "@type": "EtudiantScolarite", ... }`), qui déborde de la carte. | Critique |
| TROMBI-2 | Un bouton « Exporter les données » est proposé à un étudiant, sur la liste de ses camarades. Son effet n'a pas été testé. Question de protection des données, à trancher avec le client. | Élevée |
| TROMBI-3 | Le champ « Nom, prénom… » et le sélecteur de semestre n'ont pas d'étiquette. Toutes les photos ont le même texte alternatif, « photo de profil ». | Moyenne |

### 4.8 Recherche

| # | Constat | Gravité |
|---|---|---|
| RECH-10 | Le champ « Recherche » de la barre haute n'est relié à rien : aucun résultat à la frappe, ni à Entrée. Le moteur existe côté API (fiches D1 et D2), aucun écran ne l'appelle. | Critique |
| RECH-11 | En mobile, les deux champs de recherche sont masqués (`display: none`) : il n'y a aucune recherche sur téléphone. | Élevée |
| RECH-12 | Le champ n'a qu'un texte indicatif pour étiquette, et aucun rôle `combobox`. | Moyenne |

Mesures de l'API, pour la fiche D3 :

| Requête | Résultat | Temps |
|---|---|---|
| `devlopement web` (faute) | R1.11 Développement web | 53 ms |
| `R1.06` | R1.06 Production graphique | 51 ms |
| `gide` (faute) | 10 documents « Guide… » | 49 ms |
| `doe` | Jane Doe (étudiante, S1), John DOE (personnel) | — |
| `martin` | aucun résultat, alors que Martin Claire enseigne dans l'emploi du temps (EDT-6) | — |

Les réponses n'exposent aucune URL : la palette devra construire elle-même le lien vers chaque
résultat.

### 4.9 Barre haute et menu

| # | Constat | Gravité |
|---|---|---|
| NAV-1 | « Messages » et « Notifications » n'ont aucune action : deux entrées mortes, sur chaque page. Aucune API correspondante n'existe (proposition P3). | Élevée |
| NAV-2 | Le bouton de menu et le bouton de thème clair ou sombre n'ont pas de nom accessible. | Élevée |
| NAV-3 | Les liens du menu latéral n'ont aucun indicateur de focus (vérifié à l'écran sur « Scolarité »). Le menu n'est pas dans une région `nav`. | Élevée |

Le menu « Applications » et le menu du profil sont bien des `role="menu"`, et le focus y entre à
l'ouverture.

### 4.10 Mobile (375 x 812)

Points positifs :
- Plus aucune cible tactile sous 44 px sur les six écrans mesurés (PR #31, contre 26 sur 66 dans
  l'audit 01).
- Aucun défilement horizontal de la page.

| # | Constat | Gravité |
|---|---|---|
| MOB-6 | Le menu d'actions de la barre haute est ouvert au chargement de chaque page et recouvre le contenu. | Élevée |
| MOB-7 | L'emploi du temps s'ouvre en vue semaine : cinq colonnes d'environ 50 px, libellés coupés (« R1.0 », « Cultu »), en-têtes de jours qui se chevauchent. La vue jour est lisible, mais il faut la choisir, et le premier cours n'apparaît qu'à 575 px du haut. | Élevée |
| MOB-8 | Le libellé du bouton « Aujourd'hui » est coupé : 61 px de large pour 84 px de texte. | Moyenne |
| MOB-9 | Documents : la mise en page bureau est conservée, et la liste de documents est écrasée dans une colonne d'environ 40 px, à droite de l'arborescence. | Critique |
| MOB-10 | Portail : la carte « Mon dashboard » sort de l'écran (jusqu'à 456 px pour 375), son bouton « Configurer » est hors de portée, et elle chevauche le message d'accueil. Des blocs rognés dépassent aussi sur le tableau de bord, le trombinoscope et le profil. | Élevée |

### 4.11 Accessibilité transverse

| # | Constat | Mesure | Gravité |
|---|---|---|---|
| A11Y-7 | Aucun lien d'évitement. Le premier élément focalisable est le bouton de menu, sans nom. | — | Moyenne |
| A11Y-8 | Repères incohérents. `main` est absent du tableau de bord, de l'agenda, de la scolarité, du trombinoscope et du profil, mais présent sur le portail et les documents. Le menu latéral n'est pas une `nav`. On compte de 0 à 2 `header` selon la page. | — | Moyenne |
| A11Y-9 | Toutes les pages ont le même titre de document, « Uniservices ». | — | Moyenne |
| A11Y-10 | Primaire du portail, texte blanc sur `#EAB308`. | 1,92:1 | Élevée |
| A11Y-11 | Primaire de l'intranet, texte blanc sur `#8B5CF6` (« Retour », « Configurer », « SEMAINE »). Le violet sur blanc fait le même rapport, et le fil d'Ariane sur `#F1F5F9` fait 3,87:1. | 4,23:1 | Élevée |
| A11Y-12 | Textes secondaires : heures de l'agenda `#99A0AA` sur blanc, métadonnées des documents `#979CA8`, étoile de favori `#D1D5DC`, date du jour `#6A7282` sur `#F1F5F9`. | 2,64:1 ; 2,75:1 ; 1,47:1 ; 4,41:1 | Élevée |
| A11Y-13 | Trombinoscope : « Réinitialiser les filtres » en blanc sur `#F97316`, « Exporter les données » en blanc sur `#0EA5E9`. | 2,80:1 ; 2,77:1 | Élevée |
| A11Y-14 | Boutons icônes sans nom : menu, thème, flèches de l'agenda, chevrons des catégories. | — | Élevée |
| A11Y-15 | Champs sans étiquette : recherche de la barre haute (sur toutes les pages), recherche des documents, recherche et filtre du trombinoscope, case « Se souvenir de moi ». | — | Élevée |
| A11Y-16 | Focus invisible sur les liens du menu latéral et sur « Télécharger » dans les documents. | — | Élevée |
| A11Y-17 | Aucune région `aria-live` en dehors du message d'erreur de connexion. Le résultat d'une recherche de documents et le changement de semaine ne sont pas annoncés. | — | Moyenne |
| A11Y-18 | Les icônes PrimeIcons décoratives ne sont pas masquées : 12 à 21 par page, aucune n'a `aria-hidden`. | — | Faible |

Points positifs mesurés :
- `lang="fr"` est bien déclaré, et Roboto est chargée.
- Les boutons PrimeVue succès et danger sont conformes depuis #31.
- Le focus est visible sur les boutons PrimeVue.
- Le mode sombre n'a donné aucun échec de contraste sur l'agenda.
- Les blocs de l'emploi du temps portent du texte foncé sur fond pastel. Seul leur horaire,
  `#697275` sur `#E5F7FF`, reste juste sous le seuil : 4,49:1.

---

## 5. Direction artistique

| Jeton | DA imposée | Rendu observé |
|---|---|---|
| Primaire | `#F7B000` | une couleur Tailwind par module : `#EAB308` au portail, `#8B5CF6` dans l'intranet, `#3B82F6` dans les documents, puis orange, vert, sarcelle et émeraude pour les autres |
| Texte sur primaire | `#4D3677` | `#FFFFFF` |
| Texte courant | `#4D5259` | `#334155` |
| Fond | `#F5F6FA` | `#F1F5F9` |
| Succès, danger | `#15C377` et `#F96868`, texte foncé | conformes pour les composants PrimeVue (#31) |
| Rayon | 6 px | cartes en `rounded-3xl`, soit 21 px sur une base de 14 px |
| Police | Roboto | Roboto (#31) |

**DA-1.** L'orange de la DA n'est utilisé nulle part. Chaque module déclare sa propre couleur
primaire dans son `manifest.ts` (`primaryColor: 'yellow'`, `'violet'`, `'blue'`…), et le shell
l'applique au thème PrimeVue à chaque changement de route (`useBundleTheme.js`). L'étudiant voit
l'identité changer en passant du portail à l'intranet, puis aux documents. Posé avec du texte
blanc, c'est aussi la source de A11Y-10, A11Y-11 et A11Y-13. La correction est centrale : un seul
preset aux jetons de la DA. Il faut toutefois savoir si la couleur par module est un choix du
client (question 1). Gravité : Élevée.

---

## 6. Ce que sont devenus les constats de l'audit 01

| Constat | Statut | Aujourd'hui |
|---|---|---|
| TB-1 | Partiel | Le widget « Maintenant » est en tête du tableau de bord de l'intranet, mais pas sur le portail, qui est la page d'arrivée. « Aujourd'hui » est faux (TB-8). |
| TB-2 | Partiel | Une ligne marque l'heure courante dans l'agenda. La mise en évidence du jour courant n'a pas été vérifiée. |
| TB-3 | Sans objet | Plus de tableaux de notes ni d'absences : ils sont remplacés par une page vide (SCO-1). |
| TB-4 | Sans objet | Les modalités de contrôle ne sont plus affichées nulle part. |
| TB-5 | Autre forme | Les contacts sont désormais factices, « JOHN DOE » écrit en dur (POR-2). |
| TB-6 | Corrigé | Plus d'avertissement Celcat. |
| TB-7 | Corrigé | Plus de bouton flottant. |
| DOC-1 | Persiste | Catégories de service, ni matière ni SAE (fiche B6). |
| DOC-2 | Partiel | Les catégories sont atteignables au clavier, mais sans adresse (DOC-13). Les fiches ne le sont pas (DOC-7). |
| DOC-3 | Corrigé | Les compteurs sont affichés : 30, 14, 7, 9. |
| DOC-4 | Partiel | Recherche, tri et vue liste existent. La recherche ne tolère pas les fautes (DOC-10). Filtres dans l'URL non vérifiés. |
| DOC-5 | Autre forme | Plus de `h5` en rafale, mais plus de `h1` non plus (DOC-12). |
| DOC-6 | Aggravé | En mobile, la liste est écrasée (MOB-9). |
| RECH-1 | Corrigé côté API | Tolérance aux fautes mesurée, mais invisible pour l'étudiant (RECH-10). |
| RECH-2 | Corrigé côté API | Étudiants, personnels, documents, matières, actualités. |
| RECH-3 à RECH-8 | En attente | Pas d'interface, donc rien à mesurer : ce sont les critères de D3 et D4. |
| RECH-9 | Sans objet | Données de fixtures. |
| MOB-1 | Corrigé en vue jour | Mais la vue semaine s'ouvre par défaut (MOB-7). |
| MOB-2 | Partiel | Une seule barre de navigation temporelle, mais deux numérotations de semaine (EDT-4). |
| MOB-3 | Sans objet | Le bouton « Déposer un justificatif » n'existe pas (proposition P2). Autre débordement : MOB-8. |
| MOB-4 | Corrigé | Aucune cible sous 44 px (#31). |
| MOB-5 | Corrigé | Plus d'avertissement Celcat. |
| A11Y-1 | Corrigé | Plus de ligatures lues. Les icônes restent non masquées (A11Y-18). |
| A11Y-2, A11Y-3 | Corrigé | Pour les composants PrimeVue (#31). Les couleurs Tailwind posées à la main n'ont pas été reprises. |
| A11Y-4 | Corrigé | Texte foncé sur pastel. L'horaire des blocs est à 4,49:1. |
| A11Y-5 | Persiste | A11Y-7. |
| A11Y-6 | Autre forme | A11Y-8. |

---

## 7. Ce qui fonctionne et doit être conservé

- Le moteur de recherche : tolérant aux fautes, 5 types, entre 49 et 116 ms mesurés.
- L'emploi du temps alimenté par Celcat, avec une vue jour lisible et l'heure courante.
- Le widget « Maintenant », juste.
- Le socle de #31 : Roboto, contrastes succès et danger, cibles tactiles.
- Le mode sombre, lisible.
- Les composants PrimeVue : boîtes de dialogue structurées, focus visible, menus en
  `role="menu"`, erreur de connexion annoncée.
- Les documents : compteurs, recherche, tri, vue liste.
- Aucun défilement horizontal de page, en bureau comme en mobile.

L'audit 01 disait que l'interface exposait la structure de la base de données plutôt que les
tâches de l'étudiant. C'est encore vrai, et à la lettre : noms de modules issus du code, JSON
brut au trombinoscope, gabarits « OK » non remplis. S'y ajoute un écart : le moteur est prêt
mais aucun écran ne s'en sert.

---

## 8. Priorités révisées

Les priorités du client ne changent pas. Leur contenu, si.

| Priorité | Sujet | Constats | Fiches existantes |
|---|---|---|---|
| 1 | Recherche : brancher une interface sur l'API, en bureau et en mobile | RECH-10 à RECH-12, EDT-6 | D3, D4 |
| 2 | Documents : clavier, mobile, lisibilité, tolérance, matière et SAE | DOC-7 à DOC-13, MOB-9 | B1, B4, B5, B6 |
| 3 | Tableau de bord : une seule page d'accueil, juste, sans contenu factice | TB-8 à TB-10, POR-1 à POR-3 | C1, C5 (à revérifier), C7 |
| 4 | Emploi du temps mobile | MOB-6 à MOB-8, EDT-1 à EDT-5 | C3 |
| 5 | Design system : une primaire DA, contrastes, focus, noms, repères | DA-1, A11Y-7 à A11Y-18, CNX-1 à CNX-4, NAV-2, NAV-3 | A3 |

Hors de ces cinq priorités, quatre défauts se voient dès la première visite et méritent d'être
traités tôt :
- SCO-1 à SCO-3 : scolarité, cahier de texte et profil vides ;
- TROMBI-1 : JSON brut ;
- NAV-1 : boutons sans action ;
- EDT-2 : actions enseignant proposées à l'étudiant.

Aucun n'a encore de fiche dans le backlog.

---

## 9. Questions ouvertes

1. Une couleur primaire par module est-elle un choix du client pour uniServices, ou un écart à la
   DA de l'IUT à corriger (DA-1) ?
2. Le module Documents est « Non activé » pour MMI mais accessible. Faut-il l'activer, ou fermer
   la route ?
3. Le trombinoscope doit-il être ouvert aux étudiants, et l'export des données leur être
   proposé (TROMBI-2) ?
4. Le client a-t-il prévu le contenu de Scolarité et du cahier de texte (notes, absences, rendus),
   ou est-ce à nous de le définir (SCO-1, SCO-2) ?
5. Le widget « Notes » est-il un pense-bête destiné au personnel, ou doit-il montrer les notes de
   l'étudiant (TB-10) ?
6. Portail et tableau de bord de l'intranet : deux pages d'accueil voulues, ou une seule à
   terme ?

Les trois questions déjà en attente dans `04-reprise.md` ne sont pas répétées ici.

---

## 10. Annexe — points d'entrée observés

| Écran | Adresse | Appels API principaux |
|---|---|---|
| Connexion | `/app/auth/login` | `POST /api/login` |
| Portail | `/app/auth/portail` | `/api/widgets/catalog?dashboardCode=portail` |
| Tableau de bord | `/app/intranet` | `/api/widgets/intranet.emploi_du_temps/data`, `/api/widgets/intranet.notes/data`, `/api/edt_events?day=` |
| Emploi du temps | `/app/intranet/agenda` | `/api/edt_events?semaineFormation=&groupe[]=`, `/api/structure_calendriers?semaineReelle=` |
| Scolarité | `/app/intranet/scolarite` | `/api/all/etudiant_scolarites?etudiant=` |
| Cahier de texte | `/app/intranet/cahier-de-texte` | aucun |
| Profil | `/app/intranet/profil` | `/api/etudiants/{id}` |
| Trombinoscope | `/app/intranet/trombinoscope` | non relevé |
| Documents | `/app/documents` | `/api/documents` |
| Recherche | aucun écran | `GET /api/recherche?q=` |
