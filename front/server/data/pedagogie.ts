import { ressource } from '../utils/hydra'

export const matieres = [
  {
    ...ressource('ScolEnseignement', 501, 'scol_enseignements'),
    libelle: 'Developpement front-end avance',
    libelle_court: 'Dev front',
    codeEnseignement: 'R5.01',
    description: 'Frameworks reactifs, accessibilite et performance percue.',
    motsCles: 'vue, accessibilite, performance',
    type: 'RESSOURCE',
    sae: null,
    suspendu: false,
  },
  {
    ...ressource('ScolEnseignement', 502, 'scol_enseignements'),
    libelle: 'Referencement et strategie de contenu',
    libelle_court: 'SEO',
    codeEnseignement: 'R5.02',
    description: 'Audit, indexation, mesure.',
    motsCles: 'seo, contenu, audit',
    type: 'RESSOURCE',
    sae: null,
    suspendu: false,
  },
  {
    ...ressource('ScolEnseignement', 551, 'scol_enseignements'),
    libelle: 'SAE 501 - Concevoir une experience numerique',
    libelle_court: 'SAE 501',
    codeEnseignement: 'SAE5.01',
    description: 'Projet de conception menee de la recherche utilisateur a la livraison.',
    motsCles: 'projet, ux, livrable',
    type: 'SAE',
    sae: '/api/scol_enseignements/551',
    suspendu: false,
  },
]

export const notes = [
  {
    ...ressource('EtudiantNote', 9001, 'etudiant_notes'),
    note: 14.5,
    commentaire: null,
    publiee: true,
    presenceStatut: 'PRESENT',
    evaluation: '/api/scol_evaluations/301',
    created: '2025-10-06T09:00:00+00:00',
  },
  {
    ...ressource('EtudiantNote', 9002, 'etudiant_notes'),
    note: null,
    commentaire: 'Copie non rendue',
    publiee: false,
    presenceStatut: 'ABSENT',
    evaluation: '/api/scol_evaluations/302',
    created: '2025-10-13T09:00:00+00:00',
  },
]

export const absences = [
  {
    ...ressource('EtudiantAbsence', 4501, 'etudiant_absences'),
    justifiee: false,
    dateJustification: null,
    event: '/api/edt_events/8801',
    created: '2025-10-13T08:05:00+00:00',
  },
  {
    ...ressource('EtudiantAbsence', 4502, 'etudiant_absences'),
    justifiee: true,
    dateJustification: '2025-10-15T10:00:00+00:00',
    event: '/api/edt_events/8802',
    created: '2025-10-14T14:10:00+00:00',
  },
]

export const evenements = [
  {
    ...ressource('EdtEvent', 8801, 'edt_events'),
    date: '2025-10-13',
    debut: '08:00:00',
    fin: '10:00:00',
    salle: 'B204',
    codeModule: 'R5.01',
    libModule: 'Developpement front-end avance',
    codeGroupe: 'TP2B',
    libGroupe: 'TP2B',
    libPersonnel: 'D. Annebicque',
    type: 'TP',
    couleur: '#F7B000',
    evaluation: false,
  },
  {
    ...ressource('EdtEvent', 8802, 'edt_events'),
    date: '2025-10-14',
    debut: '14:00:00',
    fin: '16:00:00',
    salle: 'A101',
    codeModule: 'SAE5.01',
    libModule: 'SAE 501 - Concevoir une experience numerique',
    codeGroupe: 'TD2',
    libGroupe: 'TD2',
    libPersonnel: 'C. Herolt',
    type: 'TD',
    couleur: '#15C377',
    evaluation: true,
  },
]
