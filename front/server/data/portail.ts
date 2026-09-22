import type { CatalogueWidgets, ContexteSecurite, Widget } from '~~/types/domain'

/*
 * Formes relevees sur l'API reelle : /api/me/security-context, /api/widgets/catalog
 * et /api/widgets/available/{dashboardCode}. Les widgets non actives par defaut
 * gardent position a null, c'est ce qui les distingue cote API.
 */

export const contexte: ContexteSecurite = {
  user: {
    id: 12,
    username: 'etudiant',
    prenom: 'Jane',
    nom: 'Doe',
    email: 'etudiant.user@etudiant.univ-reims.fr',
    type: 'etudiants',
    roles: ['ROLE_ETUDIANT'],
  },
  packages: ['intranet', 'documents'],
  permissions: ['ROLE_ETUDIANT'],
}

export const bundles = [
  { code: 'auth', label: 'Auth' },
  { code: 'intranet', label: 'Intranet' },
  { code: 'portfolio', label: 'Portfolio' },
  { code: 'questionnaire', label: 'Questionnaire' },
  { code: 'document', label: 'Documents (GED)' },
]

export const widgets: Widget[] = [
  {
    code: 'intranet.emploi_du_temps',
    bundle: 'intranet',
    label: "Aujourd'hui",
    icon: 'pi pi-calendar',
    component: 'EmploiDuTempsWidget',
    size: 'large',
    enabled: true,
    allowedProfiles: ['personnel', 'etudiant'],
    position: 0,
    colSpan: 3,
    rowSpan: 1,
    key: 'intranet.emploi_du_temps',
  },
  {
    code: 'portfolio.progress',
    bundle: 'portfolio',
    label: 'Progression portfolio',
    icon: 'pi pi-chart-line',
    component: 'PortfolioProgressWidget',
    size: 'small',
    enabled: true,
    allowedProfiles: ['personnel', 'etudiant'],
    position: 2,
    colSpan: 1,
    rowSpan: 1,
    key: 'portfolio.progress',
  },
  {
    code: 'questionnaire.pending',
    bundle: 'questionnaire',
    label: 'Questionnaires en attente',
    icon: 'pi pi-inbox',
    component: 'QuestionnairePendingWidget',
    size: 'medium',
    enabled: true,
    allowedProfiles: ['personnel', 'etudiant'],
    position: 3,
    colSpan: 2,
    rowSpan: 1,
    key: 'questionnaire.pending',
  },
  {
    code: 'document.recents',
    bundle: 'document',
    label: 'Documents recents',
    icon: 'pi pi-file',
    component: 'DocumentsRecentsWidget',
    size: 'medium',
    enabled: true,
    allowedProfiles: ['personnel', 'etudiant'],
    position: null,
    colSpan: 1,
    rowSpan: 1,
    key: 'document.recents',
  },
  {
    code: 'intranet.notes',
    bundle: 'intranet',
    label: 'Notes',
    icon: 'pi pi-chart-bar',
    component: 'NotesWidget',
    size: 'small',
    enabled: true,
    allowedProfiles: ['personnel', 'etudiant'],
    position: null,
    colSpan: 1,
    rowSpan: 1,
    key: 'intranet.notes',
  },
]

export const catalogue: CatalogueWidgets = { bundles, widgets }

export const donneesWidgets: Record<string, unknown> = {
  'intranet.emploi_du_temps': {
    todayLabel: 'mardi 22 septembre 2026',
    items: [
      { heure: '08:00 - 10:00', cours: 'Developpement front-end avance, TP2B, salle B204' },
      { heure: '14:00 - 16:00', cours: 'SAE 501, TD2, salle A101' },
    ],
  },
  'portfolio.progress': { validated: 62, target: 100 },
  'questionnaire.pending': { items: [] },
  'document.recents': { items: [] },
  'intranet.notes': { items: [] },
}
