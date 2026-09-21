# Ce qu'on a besoin de savoir

Sprint 0. Questions non techniques, à poser au client. Pour chacune, la réponse qu'on prend par
défaut s'il ne répond pas : on n'est jamais bloqué.

---

## Les six questions

**1. Dans quatre mois, qu'est-ce qui aura changé pour les étudiants si c'est réussi ?**
Par défaut : un étudiant trouve ce qu'il cherche en moins de trois actions, depuis son téléphone.

**2. Les documents sont rangés par service (Alternance, Stages, Documents officiels, Plannings).
On pense qu'un étudiant cherche par matière ou par projet. On peut ajouter ce classement ?**
Par défaut : oui, on garde les catégories actuelles et on ajoute matière et SAE, plus « récemment
ajoutés » et des documents épinglables.

**3. Une recherche « intelligente », elle doit trouver quoi ?** Documents seuls, ou aussi
personnes, matières, cours de l'emploi du temps, messages, actualités ?
Par défaut : tout ça, documents et personnes d'abord, et elle pardonne les fautes de frappe.

**4. On garde les couleurs et le logo. Est-ce qu'on peut revoir la mise en page — l'ordre des
blocs, ce qu'on voit en premier, la densité ? Ou ça doit rester visuellement identique ?**
Par défaut : identité conservée, mise en page repensée.
C'est la question la plus rentable des six : l'écart entre les deux réponses fait plusieurs
semaines de travail.

**5. Est-ce qu'un étudiant doit pouvoir tout faire depuis son téléphone, ou c'est secondaire ?**
Par défaut : téléphone d'abord.

**6. Y a-t-il quelque chose qu'on ne doit pas toucher ou casser ?**
Par défaut : on ne modifie rien de l'existant, on construit à côté.

---

## Les deux questions liées au backoffice

À poser en même temps, parce que nos deux fonctionnalités en dépendent.

**7. Est-ce qu'un document peut déjà être rattaché à une matière ou à une SAE dans le
backoffice, ou la catégorie est-elle le seul classement possible ?**
Par défaut : on suppose que non, et on prévoit un classement déduit côté étudiant (par le nom du
fichier, par la catégorie, ou par épinglage manuel de l'étudiant).
Enjeu : si le lien document/matière n'existe pas en base, personne ne peut le renseigner, et notre
classement par matière repose sur du vide.

**8. Est-ce que notre périmètre s'arrête à l'écran étudiant, ou peut-on proposer l'écran
enseignant qui va avec quand une fonctionnalité en a besoin ?**
Par défaut : périmètre étudiant strict. Si une fonctionnalité exige une saisie côté enseignant, on
la signale et on propose une solution qui s'en passe.

---

## Si on obtient dix minutes en direct

Trois questions ouvertes, qui font plus parler qu'un questionnaire :

1. Racontez la dernière fois qu'un étudiant est venu vous voir parce qu'il ne trouvait pas quelque
   chose. Qu'est-ce qu'il cherchait ?
2. Qu'est-ce qu'on vous demande le plus souvent et qui devrait se trouver tout seul ?
3. Si on ne pouvait livrer qu'une seule chose, laquelle ?

On note, on ne propose rien pendant l'échange. Les propositions viennent après, par écrit.

---

## Ce qu'on ne demande pas

- « Quelles fonctionnalités voulez-vous ? » Il a dit qu'il n'avait pas d'idées arrêtées. On
  propose, il arbitre.
- Les choix techniques, l'architecture, le niveau de conformité. C'est notre travail, et il ne
  peut pas les évaluer.
- « Est-ce que ça vous plaît ? » sur une maquette. On demande plutôt : « un étudiant qui cherche
  le sujet de la SAE 3.01 fait ça, puis ça. Ça vous paraît juste ? »

---

## Journal des réponses

| # | Sujet | Réponse | Date | Décision retenue |
|---|---|---|---|---|
| 1 | Définition de la réussite | | | |
| 2 | Classement des documents | | | |
| 3 | Périmètre de la recherche | | | |
| 4 | Latitude visuelle | | | |
| 5 | Place du téléphone | | | |
| 6 | Limites | | | |
| 7 | Lien document / matière | | | |
| 8 | Périmètre enseignant | | | |

Une réponse orale est reformulée par écrit et renvoyée pour confirmation. Une décision non écrite
n'existe pas.
