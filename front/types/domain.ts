/*
 * Types du domaine, decalques du contrat expose par uniServices (API Platform).
 * Les noms de champs sont ceux de l'API reelle, y compris quand ils melangent
 * francais et anglais ou camelCase et snake_case : les renommer ici obligerait a
 * traduire dans les deux sens le jour de la bascule.
 */

/** Enveloppe Hydra d'une collection API Platform. */
export interface CollectionHydra<T> {
  '@context': string
  '@id': string
  '@type': 'Collection'
  totalItems: number
  member: T[]
}

/** Partie commune a toute ressource JSON-LD. */
export interface RessourceHydra {
  '@id': string
  '@type': string
  id: number
}

export interface AnneeUniversitaire extends RessourceHydra {
  libelle: string
  annee?: number
  actif: boolean
}

export interface Annee extends RessourceHydra {
  libelle: string
}

export interface Semestre extends RessourceHydra {
  libelle: string
  ordreAnnee?: number
  ordreLmd?: number
  actif: boolean
  codeElement?: string | null
  annee?: Annee
  typesGroupe?: string[]
}

export type TypeGroupe = 'CM' | 'TD' | 'TP'

export interface Groupe extends RessourceHydra {
  libelle: string
  type: TypeGroupe | string
  ordre?: number | null
  parent?: string | null
}

export interface Etudiant extends RessourceHydra {
  username: string
  prenom: string
  nom: string
  mailUniv: string
  photoName?: string | null
  num_etudiant?: string | null
  promotion?: number | null
}

/* Cette collection n'expose que l'IRI, sans `id` : elle ne suit pas RessourceHydra. */
export interface Scolarite {
  '@id': string
  '@type': string
  anneeUniversitaire: AnneeUniversitaire
  packages: string[]
}

export interface ScolariteSemestre extends RessourceHydra {
  semestre: Semestre
  groupes: Groupe[]
}

/** Une matiere est un ScolEnseignement cote API ; `sae` distingue les SAE des ressources. */
export interface Matiere extends RessourceHydra {
  libelle: string
  libelle_court?: string | null
  codeEnseignement?: string | null
  description?: string | null
  motsCles?: string | null
  type: string
  sae?: string | null
  suspendu: boolean
}

/* La matiere n'est pas portee par la note : on y accede via l'IRI `evaluation`. */
export interface Note extends RessourceHydra {
  note: number | null
  commentaire?: string | null
  publiee: boolean
  presenceStatut: string
  evaluation?: string | null
  created: string
}

export interface Absence extends RessourceHydra {
  justifiee: boolean
  dateJustification?: string | null
  event: string
  created: string
}

export interface DocumentCategorie extends RessourceHydra {
  libelle: string
  icon: string
  color: string
  parent?: string | null
  documentCount?: number
}

export interface Document extends RessourceHydra {
  titre: string
  description?: string | null
  filename: string
  mimeType: string
  fileSize: number
  type: string
  author?: string | null
  version?: string | null
  tags: string[]
  visibility: string
  category: DocumentCategorie
  createdAt: string
  updatedAt: string
}

/** Un evenement d'emploi du temps. `debut` et `fin` sont au format HH:mm:ss. */
export interface Evenement extends RessourceHydra {
  date: string | null
  debut: string | null
  fin: string | null
  salle: string
  codeModule?: string | null
  libModule?: string | null
  codeGroupe?: string | null
  libGroupe?: string | null
  libPersonnel?: string | null
  type?: string | null
  couleur?: string | null
  evaluation: boolean
}

export interface Adresse {
  pays: string
  ville: string
  adresse: string
  codePostal: string
  complement1: string
  complement2: string
}

/* Ressource unique : /api/etablissements renvoie l'etablissement, pas une collection. */
export interface Etablissement extends RessourceHydra {
  libelle: string
  logo_name: string | null
  adresse: Adresse
  site_web: string | null
}

/** Reponse de /api/auth/me : l'API ne renvoie pas de ressource JSON-LD ici. */
export interface Session {
  authenticated: boolean
  userId: number | null
  type: string | null
  username: string | null
}
