import { ressource } from '../utils/hydra'

/*
 * L'API reelle stocke un emoji dans `icon`. Le projet interdit l'emoji, on garde
 * donc le champ au contrat mais avec une cle textuelle, a mapper sur une icone
 * cote interface.
 */
export const categories = [
  {
    ...ressource('DocumentCategory', 1, 'document_categories'),
    libelle: 'Scolarite',
    icon: 'scolarite',
    color: 'bg-blue-500',
    documentCount: 2,
  },
  {
    ...ressource('DocumentCategory', 2, 'document_categories'),
    libelle: 'Stages et alternance',
    icon: 'stage',
    color: 'bg-blue-400',
    parent: '/api/document_categories/1',
    documentCount: 1,
  },
  {
    ...ressource('DocumentCategory', 3, 'document_categories'),
    libelle: 'Plannings',
    icon: 'planning',
    color: 'bg-amber-500',
    documentCount: 1,
  },
]

export const documents = [
  {
    ...ressource('Document', 101, 'documents'),
    titre: 'Reglement des etudes 2025-2026',
    description: 'Modalites de controle des connaissances du BUT MMI.',
    filename: 'reglement-etudes-2025-2026.pdf',
    mimeType: 'application/pdf',
    fileSize: 482_133,
    type: 'pdf',
    author: 'Scolarite IUT de Troyes',
    version: 'v1.0',
    tags: ['reglement', 'but'],
    visibility: 'PUBLIC',
    category: categories[0],
    createdAt: '2025-09-01T08:00:00+00:00',
    updatedAt: '2025-09-01T08:00:00+00:00',
  },
  {
    ...ressource('Document', 102, 'documents'),
    titre: 'Convention de stage - modele',
    description: 'Modele a faire signer avant le depart en stage.',
    filename: 'convention-stage-modele.docx',
    mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    fileSize: 96_412,
    type: 'document',
    author: 'Service des stages',
    version: 'v2.3',
    tags: ['stage', 'convention'],
    visibility: 'PUBLIC',
    category: categories[1],
    createdAt: '2025-09-12T10:30:00+00:00',
    updatedAt: '2025-10-02T16:45:00+00:00',
  },
  {
    ...ressource('Document', 103, 'documents'),
    titre: 'Calendrier universitaire S5',
    description: null,
    filename: 'calendrier-s5.pdf',
    mimeType: 'application/pdf',
    fileSize: 210_004,
    type: 'pdf',
    author: 'Departement MMI',
    version: 'v1.1',
    tags: ['planning', 's5'],
    visibility: 'PUBLIC',
    category: categories[2],
    createdAt: '2025-09-05T09:15:00+00:00',
    updatedAt: '2025-09-20T11:00:00+00:00',
  },
  {
    ...ressource('Document', 104, 'documents'),
    titre: 'Guide de redaction du rapport de SAE',
    description: 'Attendus de forme et de fond pour les livrables de SAE.',
    filename: 'guide-rapport-sae.pdf',
    mimeType: 'application/pdf',
    fileSize: 331_890,
    type: 'pdf',
    author: 'Equipe pedagogique MMI',
    version: 'v1.0',
    tags: ['sae', 'livrable'],
    visibility: 'PUBLIC',
    category: categories[0],
    createdAt: '2025-09-22T13:00:00+00:00',
    updatedAt: '2025-09-22T13:00:00+00:00',
  },
]
