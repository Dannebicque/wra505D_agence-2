import { ressource } from '../utils/hydra'

/*
 * Categories calquees sur celles de l'intranet reel, relevees dans l'audit : elles
 * suivent l'organigramme de l'etablissement, pas la vie de l'etudiant. On les garde
 * telles quelles, le reclassement par matiere et par SAE demande une evolution du
 * modele de donnees cote API.
 *
 * L'API reelle met un emoji dans `icon`. Le projet les interdit : on garde le champ
 * au contrat avec une cle textuelle, a mapper sur une icone cote interface.
 */
const definitionsCategories = [
  { id: 1, libelle: 'Alternance', icon: 'alternance', color: 'bg-blue-500', parent: null },
  {
    id: 2,
    libelle: 'Cellule handicap',
    icon: 'accompagnement',
    color: 'bg-violet-500',
    parent: null,
  },
  { id: 3, libelle: 'Documents officiels', icon: 'officiel', color: 'bg-slate-500', parent: null },
  { id: 4, libelle: 'Plannings', icon: 'planning', color: 'bg-amber-500', parent: null },
  {
    id: 5,
    libelle: 'Relations internationales',
    icon: 'international',
    color: 'bg-teal-500',
    parent: null,
  },
  { id: 6, libelle: 'Stages', icon: 'stage', color: 'bg-green-500', parent: null },
  { id: 7, libelle: 'Offres de stage', icon: 'offre', color: 'bg-green-400', parent: 6 },
  { id: 8, libelle: 'Conventions', icon: 'convention', color: 'bg-green-400', parent: 6 },
]

const typesFichier = [
  { type: 'pdf', mimeType: 'application/pdf', extension: 'pdf' },
  {
    type: 'document',
    mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    extension: 'docx',
  },
  {
    type: 'tableur',
    mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    extension: 'xlsx',
  },
]

const auteurs = [
  'Scolarite IUT de Troyes',
  'Service des stages',
  'Departement MMI',
  'Relations internationales',
]

/*
 * Sept intitules pour huit categories : les deux longueurs sont premieres entre
 * elles, sinon l'intitule reste constant a l'interieur d'une categorie et la liste
 * filtree semble pleine de doublons.
 */
const intitules = [
  'Reglement des etudes',
  'Calendrier universitaire',
  'Modele de convention',
  'Guide de redaction du rapport',
  'Liste des offres',
  'Notice de candidature',
  'Procedure de depot',
]

function creerDocument(index: number) {
  const categorie = definitionsCategories[index % definitionsCategories.length]!
  const fichier = typesFichier[index % typesFichier.length]!
  const intitule = intitules[index % intitules.length]!
  const jour = String((index % 28) + 1).padStart(2, '0')
  const mois = String((index % 12) + 1).padStart(2, '0')
  const date = `2025-${mois}-${jour}T09:00:00+00:00`

  return {
    ...ressource('Document', 100 + index, 'documents'),
    titre: `${intitule} ${2025 - (index % 3)}`,
    description: index % 4 === 0 ? null : `${intitule}, categorie ${categorie.libelle}.`,
    filename: `document-${100 + index}.${fichier.extension}`,
    mimeType: fichier.mimeType,
    fileSize: 48_000 + index * 1_337,
    type: fichier.type,
    author: auteurs[index % auteurs.length]!,
    version: `v1.${index % 5}`,
    tags: index % 3 === 0 ? ['but'] : ['but', 'mmi'],
    visibility: 'PUBLIC',
    category: categorieSansEnfants(categorie),
    createdAt: date,
    updatedAt: date,
  }
}

function categorieSansEnfants(definition: (typeof definitionsCategories)[number]) {
  return {
    ...ressource('DocumentCategory', definition.id, 'document_categories'),
    libelle: definition.libelle,
    icon: definition.icon,
    color: definition.color,
    ...(definition.parent === null
      ? {}
      : { parent: `/api/document_categories/${definition.parent}` }),
  }
}

/* 240 documents : le cas « plus de deux cents » doit pouvoir etre eprouve. */
export const documents = Array.from({ length: 240 }, (_, index) => creerDocument(index))

export const categories = definitionsCategories.map((definition) => ({
  ...categorieSansEnfants(definition),
  documentCount: documents.filter((document) => document.category.id === definition.id).length,
}))
