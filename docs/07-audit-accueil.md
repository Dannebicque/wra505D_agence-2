# Recette UI/UX de l'accueil étudiant

Statut : 14 octobre 2026, branche `feature/accueil-instant` au commit `5ded5ed9`.
Périmètre : la page d'accueil de l'étudiant (`/intranet/`), refondue par C18.
Question posée : en bureau, on se sent « un peu agressé » en arrivant et l'on ne comprend pas tout
d'un coup d'œil ; le mobile rend mieux, et l'emploi du temps, pourtant aussi dense, se lit bien.
Pourquoi, et que changer ?

La numérotation continue celle de l'audit 05 : TB-11 suit TB-10. Gravité sur la même échelle :
Critique, Élevée, Moyenne, Faible.

---

## 1. Méthode

- Compte `etudiant`, mercredi 14 octobre à 9 h 55 (un cours en cours), bureau 1 440 x 900 et
  mobile émulé 375 x 812, clair. L'emploi du temps sert de référence.
- Mesures par script sur le premier écran : mots, fonds, pastilles, couleurs de texte, tailles et
  graisses de police.
- Deux regards indépendants, sans hypothèse soufflée : Antigravity sur cinq captures (test des cinq
  secondes, hiérarchie) ; OpenCode sur le code (carte des couleurs, échelle typographique, motifs,
  heuristiques de Nielsen et lois de la Gestalt). Leurs constats repris ici ont été vérifiés sur
  les captures et dans le code.

## 2. Mesures du premier écran

| | Accueil bureau | Accueil mobile | Emploi du temps bureau |
|---|---|---|---|
| Mots | 226 | 69 | 209 |
| Fonds de couleur distincts | 13 | 9 | 10 |
| Pastilles | 19 | 7 | 18 |
| Couleurs de texte | 11 | 8 | 8 |
| Structures visuelles à apprendre | 9 | 1 à la fois | 1 (la grille) |
| Sens portés par le violet « CM » | 7 | 7 | 1 |
| Écart entre le plus gros texte et le texte courant | x 1,13 | x 1,13 | x 1,25 |

**Ce n'est pas la densité qui fatigue** : l'emploi du temps en montre autant. Il se lit parce qu'il
n'a qu'une structure, que la position y dit l'heure, et qu'une couleur n'y dit qu'une chose.

## 3. Ce qui marche, à garder

- Le contenu est juste et utile : où être maintenant, ce qui attend une action, les notes, les
  nouveautés. L'ordre d'importance décidé en C18 tient.
- Le mobile se lit d'un trait (Antigravity) : un seul point focal à la fois, la journée d'abord,
  les urgences au défilement.
- La liste du jour aux couleurs de l'agenda, le cours en cours marqué d'un mot.
- Accessibilité acquise : titres réels par bloc, cibles de 44 px, contrastes à 4,5:1 au moins.

## 4. Constats

| # | Constat | Gravité |
|---|---|---|
| TB-11 | **La couleur ne veut plus rien dire.** Les teintes des types de séance sont réutilisées pour tout : le violet « CM » signifie aussi échéance, source « Département », son département, fichier Word, lien « Voir la semaine » et note entre 10 et 14 ; le rouge « évaluation » signifie aussi absence à justifier, note sous 10 et fichier PDF ; le vert « TP », note à partir de 14 et fichier Excel. L'œil cherche un code, en trouve sept, et renonce. | Élevée |
| TB-12 | **Quatre à cinq points focaux se disputent l'arrivée** : le bandeau violet plein, la tuile rouge « 1 absence à justifier », la pastille rouge « 0 / 20 », les pastilles sombres « Département », la liste multicolore. Aucune n'est désignée comme la première. | Élevée |
| TB-13 | **Pas de hiérarchie typographique.** Quatre tailles sur un écart de x 1,13 (12,25 à 15,75 px), et 45 textes sur 82 en gras ou demi-gras. Tout se lit au même volume ; les titres de liste valent 700 ici, 600 là. | Élevée |
| TB-14 | **Neuf motifs visuels** à apprendre sur un écran : carte à bandeau, carte blanche à pastille d'icône, tuiles pleines, liste à séparateurs, liste à pastille de note, liste à icône ronde, cartes grises de contact à boutons bordés, pilules d'état, `Tag` PrimeVue. Trois pastilles rondes presque identiques (2 et 2,25 rem) ont trois rôles différents. | Moyenne |
| TB-15 | **Le cours en cours est dit deux fois** : en gros dans le bandeau, puis en premier dans la liste du jour, juste en dessous. Le bandeau prend 110 px pour une information que la liste porte déjà. | Moyenne |
| TB-16 | **Une même étiquette jaune pour quatre états** (En cours, Prochain cours, Journée terminée, Pas de cours) : la couleur ne distingue pas l'urgence du repos. | Faible |
| TB-17 | **Équilibre de la grille** : « À faire » s'étire à la hauteur de la journée et laisse un blanc sous ses tuiles ; la rangée du bas passe à trois colonnes étroites, puis Contacts sur toute la largeur. Trois trames sur une page. | Moyenne |
| TB-18 | **Répétitions** : la pastille « Département » sur chaque actualité (quatre fois) alors que le fil n'a souvent qu'une source ; trois messages d'erreur en texte nu, chacun à sa façon. | Faible |
| EDT-24 | Hors accueil, relevé en passant : l'emploi du temps a 38 textes à 10,5 px et 2 à 9,6 px (racine à 14 px, tailles en `rem` prévues pour 16 px). Même défaut que celui corrigé sur l'accueil. | Moyenne |

## 5. Pourquoi le mobile rend mieux

Une colonne impose un ordre de lecture : la journée, puis « À faire », puis le reste, un bloc à la
fois. En bureau, la grille montre les six blocs ensemble ; les défauts TB-11 à TB-14 deviennent
alors simultanés au lieu de successifs. Le contenu est le même : c'est la mise en scène qui
change. La cible du bureau est donc de retrouver la lecture guidée du mobile, pas d'enlever du
contenu.

## 6. La cible

1. **Une couleur, un sens.** Les teintes de séance ne servent qu'aux cours, comme sur l'agenda. Le
   rouge est réservé à ce qui demande une action (absence à justifier). Le reste est neutre : notes
   en chiffres sombres (le seuil se dit par un mot ou un repère discret, pas par une pastille
   pleine), documents par la forme de leur icône, actualités sans pastille de source sauf pour
   l'IUT. Le violet de l'IUT reste la couleur de l'identité, utilisée une fois.
2. **Un point focal.** La journée est la figure, le reste est le fond. Le bandeau ne répète plus la
   liste : il porte le bonjour, la date et une phrase d'état ; la liste porte les cours, le cours en
   cours y est mis en avant. Option à trancher : bandeau violet allégé (moins haut, sans les deux
   petits cercles) ou en-tête clair avec le violet en filet.
3. **Trois niveaux de texte.** Bonjour et état autour de 20 px, titres de bloc 16 px demi-gras,
   texte 14 px régulier, détails 13 px atténués. Le gras réservé aux titres et à la valeur clé
   (salle, note).
4. **Un motif de liste.** Une seule ligne type pour notes, documents, actualités et « À faire » :
   repère à gauche, libellé, détail atténué, valeur à droite. Les tuiles pleines deviennent des
   lignes, la couleur passe en liseré ou en icône.
5. **Une trame.** Deux colonnes en bureau : à gauche la journée, à droite « À faire » puis les
   notes ; dessous, documents, actualités et contacts sur la même trame. Aucun bloc étiré à vide.

## 7. Plan proposé

| Lot | Contenu | Constats |
|---|---|---|
| C19.1 | Couleurs : teintes de séance réservées aux cours, rouge réservé à l'action, notes, documents et actualités neutres | TB-11, TB-12, TB-16, TB-18 |
| C19.2 | Échelle typographique à trois niveaux, gras limité | TB-13 |
| C19.3 | Bandeau sans doublon avec la liste, une seule étiquette d'état lisible | TB-12, TB-15 |
| C19.4 | Motif de liste unique pour les blocs secondaires | TB-14 |
| C19.5 | Trame à deux colonnes, sans bloc étiré | TB-17 |
| C19.6 | Emploi du temps : aucun texte sous 12 px | EDT-24, non retenu (§8) |

Chaque lot se vérifie par la même mesure qu'au §2, refaite après : on vise, sur le premier écran
en bureau, une couleur par sens, un écart d'au moins x 1,4 entre l'état et le texte courant, et
au plus trois motifs.

## 8. Décisions (LCS, 14/10/2026)

1. Notes : la couleur par seuil reste, en discret : la valeur seule se colore, sans pastille.
2. Bandeau : violet allégé, moins haut, un seul cercle.
3. L'emploi du temps n'est pas touché : C19.6 et EDT-24 ne sont pas retenus.

Disposition retenue pour C19.5 : deux colonnes, qui suivent l'ordre d'importance du code. À
gauche la journée, « À faire » et les notes ; à droite les documents, les actualités et les
contacts. Le téléphone garde le même ordre en une colonne.

## 9. Après C19 (14/10/2026)

Même mesure, même écran, contenu égal :

| | Avant | Après |
|---|---|---|
| Mots | 226 | 223 |
| Fonds de couleur distincts | 13 | 10 |
| Blocs teintés | 14 | 8 |
| Pastilles de texte | 19 | 4, les types de séance |
| Couleurs de texte | 11 | 8 |
| Textes en gras ou demi-gras | 45 sur 82 | 24 sur 79 |
| Écart entre le plus gros texte et le texte courant | x 1,13 | x 1,5 |
| Motifs | 9 | 3 : carte de la journée, ligne de bloc, carte de contact |

Couleurs : les teintes de séance ne marquent plus que les cours ; le rouge, l'absence à justifier
et, en discret, la valeur d'une note sous 10. Contrastes : 4,51:1 au plus bas en clair (salle d'un
cours passé), 5,47:1 en sombre.

## 10. Version retenue (14/10/2026)

La version sobre du §9 lisait mieux mais avait perdu la touche qui distingue l'accueil d'un
tableau de bord d'école ; la disposition restait la cause principale de l'illisibilité. Retenue
avec LCS après quatre itérations :

- **Trois étages.** 1. La journée : à gauche une carte violette aux cercles du logo (bonjour, date,
  semaine de formation, un message du jour, la charge de la semaine en barres, « En ce moment »
  et « Fin de journée ») ; à droite la journée comme sur l'emploi du temps, mêmes cartes
  (`CoursCarte`), hauteur proportionnelle à la durée, pauses visibles, trait de l'heure. 2. Notes
  puis « Absences et à faire » : fond blanc, éléments en couleur (tuiles teintées par sens,
  pastilles de note douces par seuil). 3. Documents, actualités, contacts : blancs, la couleur
  seulement au survol.
- **Message du jour** tiré à chaque chargement parmi ceux qui s'appliquent (vacances proches,
  bon courage, bonne journée, rentrée, week-end…), l'anniversaire toujours en premier ; aucune
  astuce. 31 caractères au plus : il tient sur une ligne.
- **Téléphone** : carte violette réduite à l'essentiel (ni semaine de formation, ni message, ni
  barres), la journée à 56 px par heure, toujours en tête.
- Données de la journée lues une fois (`useJournee`) pour les deux cartes ; logique en fonctions
  pures testées (`accueil.js`, 39 tests).

