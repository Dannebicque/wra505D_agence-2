import { ressource } from '../utils/hydra'

/* L'API expose l'etablissement courant en ressource unique, pas en collection. */
export const etablissement = {
  '@context': '/api/contexts/Etablissement',
  '@id': '/api/etablissements',
  '@type': 'Etablissement',
  id: 1,
  libelle: 'IUT de Troyes',
  logo_name: 'logo_iut.png',
  adresse: {
    pays: 'France',
    ville: 'Rosieres-pres-Troyes',
    adresse: '9 rue de Quebec 10430 Rosieres-pres-Troyes',
    codePostal: '',
    complement1: '',
    complement2: '',
  },
  site_web: 'https://www.univ-reims.fr/iut-troyes',
}

export const anneesUniversitaires = [
  {
    ...ressource('StructureAnneeUniversitaire', 1, 'structure_annee_universitaires'),
    libelle: '2024/2025',
    annee: 2024,
    actif: false,
  },
  {
    ...ressource('StructureAnneeUniversitaire', 2, 'structure_annee_universitaires'),
    libelle: '2025/2026',
    annee: 2025,
    actif: true,
  },
]

const anneeMmi3 = { ...ressource('StructureAnnee', 3, 'structure_annees'), libelle: 'MMI 3' }

export const semestres = [
  {
    ...ressource('StructureSemestre', 5, 'structure_semestres'),
    libelle: 'S5',
    ordreAnnee: 1,
    ordreLmd: 5,
    actif: true,
    codeElement: 'MMI5',
    annee: anneeMmi3,
    typesGroupe: ['CM', 'TD', 'TP'],
  },
  {
    ...ressource('StructureSemestre', 6, 'structure_semestres'),
    libelle: 'S6',
    ordreAnnee: 2,
    ordreLmd: 6,
    actif: false,
    codeElement: 'MMI6',
    annee: anneeMmi3,
    typesGroupe: ['CM', 'TD', 'TP'],
  },
]

export const groupes = [
  { ...ressource('StructureGroupe', 12, 'structure_groupes'), libelle: 'CM', type: 'CM', ordre: 1 },
  {
    ...ressource('StructureGroupe', 13, 'structure_groupes'),
    libelle: 'TD2',
    type: 'TD',
    ordre: 2,
  },
  {
    ...ressource('StructureGroupe', 14, 'structure_groupes'),
    libelle: 'TP2B',
    type: 'TP',
    ordre: 4,
  },
]

/* Sur cette collection l'API n'expose que l'IRI, sans `id` : on s'aligne. */
export const scolarites = [
  {
    '@id': '/api/etudiant_scolarites/41',
    '@type': 'EtudiantScolarite',
    anneeUniversitaire: anneesUniversitaires[1],
    packages: ['intranet'],
  },
]

export const scolariteSemestres = [
  {
    ...ressource('EtudiantScolariteSemestre', 77, 'etudiant_scolarite_semestres'),
    semestre: semestres[0],
    groupes,
  },
]
