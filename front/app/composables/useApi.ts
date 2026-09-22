import type {
  Absence,
  CatalogueWidgets,
  ContexteSecurite,
  CollectionHydra,
  Document,
  DocumentCategorie,
  Etablissement,
  Evenement,
  Matiere,
  Note,
  Scolarite,
  ScolariteSemestre,
  Semestre,
  Session,
  Widget,
} from '~~/types/domain'

/*
 * Couche d'acces unique aux donnees. Aucun composant n'appelle $fetch.
 *
 * Point de bascule : `apiBase` vaut '' en developpement, les requetes partent donc
 * sur le Nitro local (`server/api/`), qui rejoue les chemins et les formats de
 * uniServices. Renseigner NUXT_PUBLIC_API_BASE=http://127.0.0.1:8000 fait taper
 * les memes appels sur l'API Symfony reelle, sans toucher a une ligne d'appelant.
 */
export function useApi() {
  const apiBase = useRuntimeConfig().public.apiBase

  // Pendant le rendu serveur, le cookie de session n'est pas transmis tout seul :
  // il faut le repasser depuis la requete entrante, sinon l'API repond 401.
  const cookieEntrant = import.meta.server ? useRequestHeaders(['cookie']) : {}

  function requete<T>(chemin: string, options: Parameters<typeof $fetch>[1] = {}) {
    return $fetch<T>(chemin, {
      baseURL: apiBase,
      // L'API reelle depose le JWT dans un cookie httpOnly : il doit suivre.
      credentials: 'include',
      ...options,
      headers: { Accept: 'application/ld+json', ...cookieEntrant, ...options.headers },
    })
  }

  return {
    seConnecter: (username: string, password: string) =>
      requete<null>('/api/login', { method: 'POST', body: { username, password } }),

    seDeconnecter: () => requete<null>('/api/logout', { method: 'POST' }),

    session: () => requete<Session>('/api/auth/me'),

    contexteSecurite: () => requete<ContexteSecurite>('/api/me/security-context'),

    widgetsDuTableauDeBord: (tableauDeBord: string) =>
      requete<{ widgets: Widget[] }>(`/api/widgets/available/${tableauDeBord}`),

    catalogueWidgets: () => requete<CatalogueWidgets>('/api/widgets/catalog'),

    donneesWidget: <T>(code: string) => requete<T>(`/api/widgets/${code}/data`),

    etablissement: () => requete<Etablissement>('/api/etablissements'),

    scolarites: () => requete<CollectionHydra<Scolarite>>('/api/user/etudiant_scolarites'),

    scolariteSemestres: () =>
      requete<CollectionHydra<ScolariteSemestre>>('/api/etudiant_scolarite_semestres'),

    semestres: () => requete<CollectionHydra<Semestre>>('/api/structure_semestres'),

    matieres: () => requete<CollectionHydra<Matiere>>('/api/scol_enseignements'),

    notes: () => requete<CollectionHydra<Note>>('/api/etudiant_notes'),

    absences: () => requete<CollectionHydra<Absence>>('/api/absence/etudiant_scolarite_semestres'),

    documents: () => requete<CollectionHydra<Document>>('/api/documents'),

    categoriesDocument: () =>
      requete<CollectionHydra<DocumentCategorie>>('/api/document_categories'),

    evenements: () => requete<CollectionHydra<Evenement>>('/api/edt_events'),
  }
}
