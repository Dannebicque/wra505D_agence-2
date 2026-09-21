# Contexte du projet

Fiche courte. Le détail est dans `PROJET.md` : ne l'ouvrir que pour un arbitrage de périmètre,
le pitch ou une décision d'équipe.

## Client

Le responsable des outils informatiques de l'IUT de Troyes. Il veut moderniser l'intranet
existant et répondre aux attentes des étudiants. Il n'a pas d'idées arrêtées sur les
fonctionnalités : c'est à nous de proposer, à lui d'arbitrer. Il est aussi QA du projet, donc il
lira le code.

Un seul interlocuteur avec lui : le Product Owner.

## Existant

L'intranet réel est **intranetV3** (Symfony 6, Twig, Bootstrap 5, dépôt public
`Dannebicque/intranetV3`, licence MPL-2.0). Il rend du HTML côté serveur sur 167 routes. Il n'y a
pas de contrat d'API JSON documenté à ce jour. Les emplois du temps viennent de Celcat, la
scolarité d'Apogée. Un backoffice existe à `/fr/administration/`, inaccessible à un compte
étudiant.

L'information que cherchent les étudiants est répartie entre trois silos qui ne se parlent pas :
le tableau de bord (emploi du temps, notes, absences), les documents (neuf catégories
administratives), et la messagerie, rangée sous un onglet nommé « Applications ».

## Notre périmètre

La partie étudiante uniquement. On ne touche ni au personnel, ni à l'administration, ni au code
existant. On construit à côté, avec des données simulées, derrière une couche d'accès unique
prête à basculer sur la vraie API quand le client la fournira.

## Non négociable

- Direction artistique conservée : couleurs, logo, identité.
- Accessibilité RGAA, niveau AA.
- Mobile d'abord sur les écrans étudiants.

## Priorités, dans l'ordre

1. Recherche universelle, tolérante aux fautes, multi-types, accessible au clavier.
2. Réorganisation documentaire par matière et par SAE.
3. Tableau de bord recentré sur l'instant : prochain cours, salle, échéances, absences à justifier.
4. Emploi du temps mobile.
5. Normalisation du design system, contrastes compris.

## Rôles

| Rôle | Qui | Responsabilité |
|---|---|---|
| Product Owner | Lucas | relation client, backlog, validation des PR vers `develop` |
| Développeur | à compléter | fonctionnalités, tests, PR |
| Développeur | à compléter | fonctionnalités, tests, PR |
| QA | le client | validation des PR vers `main` |
