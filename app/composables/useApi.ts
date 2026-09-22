import type {
  Absence,
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

  function requete<T>(chemin: string, options: Parameters<typeof $fetch>[1] = {}) {
    return $fetch<T>(chemin, {
      baseURL: apiBase,
      // L'API reelle depose le JWT dans un cookie httpOnly : il doit suivre.
      credentials: 'include',
      headers: { Accept: 'application/ld+json' },
      ...options,
    })
  }

  return {
    seConnecter: (username: string, password: string) =>
      requete<null>('/api/login', { method: 'POST', body: { username, password } }),

    seDeconnecter: () => requete<null>('/api/logout', { method: 'POST' }),

    session: () => requete<Session>('/api/auth/me'),

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
